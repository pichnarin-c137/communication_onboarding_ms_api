<?php

namespace App\Services\Tracking;

use App\Events\TrainerLocationUpdated;
use App\Exceptions\Business\LocationPingRejectedException;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TrainerTrackingService
{
    public const KEY_LOCATIONS = 'trainer:locations';

    public const KEY_CUSTOMER_LOCATIONS = 'customer:locations';

    public const KEY_GEOFENCE_TARGETS = 'geofence:targets';

    public function __construct(
        private AnomalyDetectionService $anomalyService,
    ) {}

    public static function trailKey(string $trainerId): string
    {
        return "trainer:{$trainerId}:trail";
    }

    public static function statusKey(string $trainerId): string
    {
        return "trainer:{$trainerId}:status";
    }

    public static function etaKey(string $trainerId): string
    {
        return "trainer:{$trainerId}:eta";
    }

    private const ACTIVE_TRACKING_STATUSES = ['en_route', 'arrived', 'in_session'];

    /**
     * Process an incoming GPS ping from a trainer's device.
     * Returns true when the trainer is in an active tracking state (trail written, geofence checked, broadcast fired).
     * Returns false when idle (at_office/completed) — only the Redis position is refreshed.
     * @throws LocationPingRejectedException
     */
    public function processPing(string $trainerId, array $data): bool
    {
        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $accuracy = (float) $data['accuracy'];
        $speed = isset($data['speed']) ? (float) $data['speed'] : null;
        $timestamp = Carbon::parse($data['timestamp']);

        // Validate accuracy
        $maxAccuracy = config('coms.tracking.max_accuracy_meters', 100);
        if ($accuracy > $maxAccuracy) {
            throw new LocationPingRejectedException(
                "Accuracy {$accuracy}m exceeds maximum {$maxAccuracy}m.",
                context: ['trainer_id' => $trainerId, 'accuracy' => $accuracy]
            );
        }

        // Validate timestamp freshness
        $maxAge = config('coms.tracking.max_ping_age_seconds', 60);
        if ($timestamp->diffInSeconds(now(), false) > $maxAge) {
            throw new LocationPingRejectedException(
                'Ping timestamp is too old.',
                context: ['trainer_id' => $trainerId, 'age_seconds' => $timestamp->diffInSeconds(now())]
            );
        }

        // Validate speed between consecutive pings (GPS spoofing check)
        $maxSpeedKmh = config('coms.tracking.max_speed_kmh', 200);
        if ($accuracy == 0) {
            $this->anomalyService->checkGpsSpoofing($trainerId, $data);
            throw new LocationPingRejectedException(
                'Accuracy of exactly 0m indicates GPS spoofing.',
                context: ['trainer_id' => $trainerId]
            );
        }

        if ($speed !== null && $speed > $maxSpeedKmh) {
            $this->anomalyService->checkGpsSpoofing($trainerId, $data);
            throw new LocationPingRejectedException(
                "Speed {$speed} km/h exceeds maximum {$maxSpeedKmh} km/h.",
                context: ['trainer_id' => $trainerId, 'speed' => $speed]
            );
        }

        // Always refresh the Redis GEO position (lightweight — keeps the live map current)
        Redis::geoadd(self::KEY_LOCATIONS, $lng, $lat, $trainerId);

        // Skip trail, geofence, and broadcast when trainer is not actively moving
        if (! $this->isTrackingActive($trainerId)) {
            return false;
        }

        // Append breadcrumb to trail list
        $breadcrumb = json_encode([
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => $accuracy,
            'speed' => $speed,
            'timestamp' => $timestamp->setTimezone(app()->bound('request.timezone') ? app('request.timezone') : 'Asia/Phnom_Penh')->format('Y-m-d\TH:i:s.uP'),
        ]);
        Redis::rpush(self::trailKey($trainerId), $breadcrumb);

        // Check geofence
        $this->checkGeofence($trainerId, $lat, $lng, $accuracy);

        // Fire Pusher event
        try {
            TrainerLocationUpdated::dispatch(
                $trainerId,
                $lat,
                $lng,
                $speed,
                $accuracy,
                $timestamp->setTimezone(app()->bound('request.timezone') ? app('request.timezone') : 'Asia/Phnom_Penh')->format('Y-m-d\TH:i:s.uP')
            );
        } catch (\Throwable $e) {
            Log::error('TrainerLocationUpdated broadcast failed', [
                'trainer_id' => $trainerId,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Returns true when the trainer is in a state where GPS trail and broadcasts are meaningful.
     */
    public function isTrackingActive(string $trainerId): bool
    {
        $cached = Redis::get(self::statusKey($trainerId));
        $status = $cached ? (json_decode($cached, true)['status'] ?? 'at_office') : 'at_office';

        return in_array($status, self::ACTIVE_TRACKING_STATUSES);
    }

    /**
     * Check if trainer is within geofence of their target customer.
     */
    public function checkGeofence(string $trainerId, float $lat, float $lng, float $accuracy): void
    {
        $targetCustomerId = Redis::hget(self::KEY_GEOFENCE_TARGETS, $trainerId);
        if (! $targetCustomerId) {
            return;
        }

        $geofenceAccuracy = config('coms.tracking.geofence_accuracy_meters', 50);
        if ($accuracy > $geofenceAccuracy) {
            return;
        }

        // Get customer position from Redis GEO and calculate distance
        $customerPos = Redis::geopos(self::KEY_CUSTOMER_LOCATIONS, $targetCustomerId);
        if (! $customerPos || ! $customerPos[0]) {
            return;
        }
        $customerLng = (float) $customerPos[0][0];
        $customerLat = (float) $customerPos[0][1];
        $distanceMeters = $this->haversineDistance($lat, $lng, $customerLat, $customerLng);

        // Use the client's configured geofence radius if available, otherwise default
        $client = Client::find($targetCustomerId);
        $geofenceRadius = $client?->geofence_radius ?? config('coms.tracking.geofence_radius_meters', 200);

        if ($distanceMeters <= $geofenceRadius) {
            // Auto-arrive: resolve via TrainerStatusService (injected at call site to avoid circular DI)
            try {
                $statusService = app(TrainerStatusService::class);
                $currentStatus = $statusService->getCurrentStatus($trainerId);

                if ($currentStatus === 'en_route') {
                    $statusService->changeStatus($trainerId, 'arrived', [
                        'customer_id' => $targetCustomerId,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'detection_method' => 'geofence',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Geofence auto-arrival failed', [
                    'trainer_id' => $trainerId,
                    'customer_id' => $targetCustomerId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get all trainers' current positions from Redis.
     */
    public function getAllTrainerPositions(): array
    {
        // Get all trainers from DB (always show every trainer, even if not actively tracked)
        $trainers = User::whereHas('role', fn ($q) => $q->where('role', 'trainer'))
            ->get(['id', 'first_name', 'last_name']);

        // Get active GPS positions from Redis
        $members = Redis::zrange(self::KEY_LOCATIONS, 0, -1);
        $activePositions = [];
        foreach ($members as $memberId) {
            $pos = Redis::geopos(self::KEY_LOCATIONS, $memberId);
            if ($pos && $pos[0]) {
                $activePositions[$memberId] = [
                    'lng' => (float) $pos[0][0],
                    'lat' => (float) $pos[0][1],
                ];
            }
        }

        $positions = [];
        foreach ($trainers as $user) {
            $statusJson = Redis::get(self::statusKey($user->id));
            $status = $statusJson ? json_decode($statusJson, true) : [];
            $gps = $activePositions[$user->id] ?? null;

            $positions[] = [
                'id' => $user->id,
                'name' => trim($user->first_name.' '.$user->last_name),
                'lng' => $gps['lng'] ?? ($status['lng'] ?? null),
                'lat' => $gps['lat'] ?? ($status['lat'] ?? null),
                'status' => $status['status'] ?? 'at_office',
                'customer_id' => $status['customer_id'] ?? null,
                'customer_name' => $status['customer_name'] ?? null,
                'updated_at' => $status['updated_at'] ?? null,
            ];
        }

        return $positions;
    }

    /**
     * Get the GPS trail breadcrumbs from Redis for a trainer.
     */
    public function getTrail(string $trainerId): array
    {
        $trail = Redis::lrange(self::trailKey($trainerId), 0, -1);

        return array_map(fn ($item) => json_decode($item, true), $trail);
    }

    /**
     * Flush Redis trail to PostGIS trainer_location_pings table.
     */
    public function flushTrailToDatabase(string $trainerId): int
    {
        $trail = Redis::lrange(self::trailKey($trainerId), 0, -1);
        if (empty($trail)) {
            return 0;
        }

        $rows = [];
        foreach ($trail as $item) {
            $point = json_decode($item, true);
            if (! $point || ! isset($point['lat'], $point['lng'])) {
                continue;
            }
            $rows[] = $point;
        }

        if (empty($rows)) {
            return 0;
        }

        // Batch insert using multi-row VALUES for PostGIS point creation
        $placeholders = [];
        $bindings = [];
        foreach ($rows as $point) {
            $placeholders[] = '(gen_random_uuid(), ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), ?, ?, ?, NOW(), NOW())';
            $bindings[] = $trainerId;
            $bindings[] = $point['lng'];
            $bindings[] = $point['lat'];
            $bindings[] = $point['accuracy'] ?? null;
            $bindings[] = $point['speed'] ?? null;
            $bindings[] = $point['timestamp'] ?? now()->toISOString();
        }

        DB::statement(
            'INSERT INTO trainer_location_pings (id, trainer_id, location, accuracy, speed, pinged_at, created_at, updated_at)
             VALUES '.implode(', ', $placeholders),
            $bindings
        );

        // Clear the Redis trail
        Redis::del(self::trailKey($trainerId));

        return count($rows);
    }

    /**
     * Get all trainer IDs that are currently en route (from geofence targets hash).
     */
    public function getEnRouteTrainerIds(): array
    {
        return Redis::hkeys(self::KEY_GEOFENCE_TARGETS) ?: [];
    }

    /**
     * Set a geofence target for a trainer.
     */
    public function setGeofenceTarget(string $trainerId, string $customerId, float $customerLat, float $customerLng): void
    {
        Redis::hset(self::KEY_GEOFENCE_TARGETS, $trainerId, $customerId);
        Redis::geoadd(self::KEY_CUSTOMER_LOCATIONS, $customerLng, $customerLat, $customerId);
    }

    /**
     * Clear geofence target for a trainer.
     */
    public function clearGeofenceTarget(string $trainerId): void
    {
        Redis::hdel(self::KEY_GEOFENCE_TARGETS, $trainerId);
    }

    /**
     * Clean up all Redis keys for a trainer (on completed/returned).
     */
    public function cleanupTrainerKeys(string $trainerId): void
    {
        Redis::zrem(self::KEY_LOCATIONS, $trainerId);
        Redis::del(self::trailKey($trainerId));
        Redis::del(self::etaKey($trainerId));
        Redis::hdel(self::KEY_GEOFENCE_TARGETS, $trainerId);
    }

    /**
     * Haversine formula: calculate distance between two coordinates in meters.
     */
    public function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
