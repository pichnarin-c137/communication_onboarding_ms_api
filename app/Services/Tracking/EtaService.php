<?php

namespace App\Services\Tracking;

use App\Events\TrainerEtaUpdated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class EtaService
{
    public function __construct(
        private TrainerTrackingService $trackingService,
    ) {}

    /**
     * Calculate ETA from one point to another via OSRM.
     */
    public function calculateEta(
        string $trainerId,
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng
    ): ?array {
        $baseUrl = config('coms.tracking.osrm_base_url', 'https://router.project-osrm.org');

        try {
            $response = Http::timeout(10)->get(
                "{$baseUrl}/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}",
                [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                ]
            );

            if (! $response->successful()) {
                Log::warning('OSRM API returned non-success', [
                    'trainer_id' => $trainerId,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $body = $response->json();
            if (($body['code'] ?? '') !== 'Ok' || empty($body['routes'])) {
                return null;
            }

            $route = $body['routes'][0];
            $etaMinutes = round($route['duration'] / 60, 1);
            $distanceMeters = (int) $route['distance'];
            $geometry = $route['geometry'] ?? null;

            $result = [
                'eta_minutes' => $etaMinutes,
                'distance_meters' => $distanceMeters,
                'route_geometry' => $geometry,
                'calculated_at' => now()->toISOString(),
            ];

            // Cache in Redis
            $ttl = config('coms.tracking.eta_cache_ttl', 120);
            Redis::setex(
                TrainerTrackingService::etaKey($trainerId),
                $ttl,
                json_encode($result)
            );

            return $result;
        } catch (\Throwable $e) {
            Log::error('OSRM ETA calculation failed', [
                'trainer_id' => $trainerId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Recalculate ETA for all en-route trainers.
     */
    public function recalculateForAllEnRoute(): int
    {
        $trainerIds = $this->trackingService->getEnRouteTrainerIds();
        $count = 0;

        foreach ($trainerIds as $trainerId) {
            // Get trainer's current position from Redis
            $trainerPos = Redis::geopos(TrainerTrackingService::KEY_LOCATIONS, $trainerId);
            if (! $trainerPos || ! $trainerPos[0]) {
                continue;
            }

            $fromLng = (float) $trainerPos[0][0];
            $fromLat = (float) $trainerPos[0][1];

            // Get target customer ID
            $customerId = Redis::hget(TrainerTrackingService::KEY_GEOFENCE_TARGETS, $trainerId);
            if (! $customerId) {
                continue;
            }

            // Get customer position from Redis
            $customerPos = Redis::geopos(TrainerTrackingService::KEY_CUSTOMER_LOCATIONS, $customerId);
            if (! $customerPos || ! $customerPos[0]) {
                continue;
            }

            $toLng = (float) $customerPos[0][0];
            $toLat = (float) $customerPos[0][1];

            $result = $this->calculateEta($trainerId, $fromLat, $fromLng, $toLat, $toLng);

            if ($result) {
                try {
                    TrainerEtaUpdated::dispatch(
                        $trainerId,
                        $result['eta_minutes'],
                        $result['distance_meters'],
                        $result['route_geometry']
                    );
                } catch (\Throwable $e) {
                    Log::error('TrainerEtaUpdated broadcast failed', [
                        'trainer_id' => $trainerId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $count++;
            }
        }

        return $count;
    }

    /**
     * Get cached ETA for a trainer.
     */
    public function getCachedEta(string $trainerId): ?array
    {
        $cached = Redis::get(TrainerTrackingService::etaKey($trainerId));
        return $cached ? json_decode($cached, true) : null;
    }
}
