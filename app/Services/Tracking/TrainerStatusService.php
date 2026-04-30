<?php

namespace App\Services\Tracking;

use App\Events\TrainerStatusChanged;
use App\Exceptions\Business\InvalidTrainerStatusTransitionException;
use App\Models\Client;
use App\Models\TrainerActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class TrainerStatusService
{
    private const TRANSITIONS = [
        'at_office' => ['en_route'],
        'en_route' => ['arrived', 'in_session'],
        'arrived' => ['in_session'],
        'in_session' => ['completed'],
        'completed' => ['at_office'],
    ];

    public function __construct(
        private readonly TrainerTrackingService $trackingService,
    ) {}

    /**
     * Change a trainer's tracking status.
     *
     * @throws InvalidTrainerStatusTransitionException|Throwable
     */
    public function changeStatus(string $trainerId, string $newStatus, array $data): TrainerActivityLog
    {
        $currentStatus = $this->getCurrentStatus($trainerId);

        // Validate transition
        $allowed = self::TRANSITIONS[$currentStatus] ?? [];
        if (! in_array($newStatus, $allowed)) {
            throw new InvalidTrainerStatusTransitionException(
                "Cannot transition from '$currentStatus' to '$newStatus'.",
                context: [
                    'trainer_id' => $trainerId,
                    'current_status' => $currentStatus,
                    'requested_status' => $newStatus,
                ]
            );
        }

        $customerId = $data['customer_id'] ?? null;
        $appointmentId = $data['appointment_id'] ?? null;
        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $detectionMethod = $data['detection_method'] ?? 'manual';

        // Create activity log record with PostGIS point
        $log = DB::transaction(function () use ($trainerId, $newStatus, $customerId, $appointmentId, $lat, $lng, $detectionMethod) {
            $logData = [
                'trainer_id' => $trainerId,
                'customer_id' => $customerId,
                'appointment_id' => $appointmentId,
                'status' => $newStatus,
                'accuracy' => null,
                'speed' => null,
                'detection_method' => $detectionMethod,
            ];

            $log = TrainerActivityLog::create($logData);

            // Update PostGIS location if coordinates provided
            if ($lat !== null && $lng !== null) {
                DB::statement(
                    'UPDATE trainer_activity_logs SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$lng, $lat, $log->id]
                );
            }

            return $log;
        });

        // Update Redis status cache
        $customerName = null;
        if ($customerId) {
            $client = Client::find($customerId);
            $customerName = $client?->company_name;
        }

        $statusData = [
            'status' => $newStatus,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'appointment_id' => $appointmentId,
            'lat' => $lat,
            'lng' => $lng,
            'detection_method' => $detectionMethod,
            'updated_at' => now()->setTimezone(app()->bound('request.timezone') ? app('request.timezone') : config('coms.user_settings.defaults.timezone', 'Asia/Phnom_Penh'))->format('Y-m-d\TH:i:s.uP'),
        ];

        $ttl = config('coms.tracking.status_cache_ttl', 86400);
        Redis::setex(TrainerTrackingService::statusKey($trainerId), $ttl, json_encode($statusData));

        // Store initial position in Redis GEO so trainer appears on the live map immediately
        if ($lat !== null && $lng !== null) {
            Redis::geoadd(TrainerTrackingService::KEY_LOCATIONS, $lng, $lat, $trainerId);
        }

        // Per-status side effects
        $this->handleSideEffects($trainerId, $newStatus, $customerId);

        // Fire Pusher event
        try {
            TrainerStatusChanged::dispatch(
                $trainerId,
                $newStatus,
                $customerId,
                $customerName,
                $lat,
                $lng,
                $detectionMethod
            );
        } catch (Throwable $e) {
            Log::error('TrainerStatusChanged broadcast failed', [
                'trainer_id' => $trainerId,
                'status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * Get the current tracking status for a trainer.
     */
    public function getCurrentStatus(string $trainerId): string
    {
        // Try Redis first
        $cached = Redis::get(TrainerTrackingService::statusKey($trainerId));
        if ($cached) {
            $data = json_decode($cached, true);

            return $data['status'] ?? 'at_office';
        }

        // Fallback to database
        $latest = TrainerActivityLog::where('trainer_id', $trainerId)
            ->orderByDesc('created_at')
            ->first();

        return $latest?->status ?? 'at_office';
    }

    /**
     * Force-reset a trainer to at_office (e.g. when an active appointment is canceled/rescheduled).
     * Flushes trail, cleans up Redis keys, and updates status cache.
     */
    public function resetToAtOffice(string $trainerId): void
    {
        $this->trackingService->flushTrailToDatabase($trainerId);
        $this->trackingService->cleanupTrainerKeys($trainerId);

        $statusData = [
            'status' => 'at_office',
            'customer_id' => null,
            'customer_name' => null,
            'appointment_id' => null,
            'lat' => null,
            'lng' => null,
            'detection_method' => 'system',
            'updated_at' => now()->setTimezone(app()->bound('request.timezone') ? app('request.timezone') : config('coms.user_settings.defaults.timezone', 'Asia/Phnom_Penh'))->format('Y-m-d\TH:i:s.uP'),
        ];

        $ttl = config('coms.tracking.status_cache_ttl', 86400);
        Redis::setex(TrainerTrackingService::statusKey($trainerId), $ttl, json_encode($statusData));

        TrainerActivityLog::create([
            'trainer_id' => $trainerId,
            'status' => 'at_office',
            'detection_method' => 'system',
        ]);

        try {
            TrainerStatusChanged::dispatch($trainerId, 'at_office', null, null, null, null, 'system');
        } catch (Throwable $e) {
            Log::error('TrainerStatusChanged broadcast failed during reset', [
                'trainer_id' => $trainerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle per-status side effects.
     */
    private function handleSideEffects(
        string $trainerId,
        string $newStatus,
        ?string $customerId
    ): void {
        switch ($newStatus) {
            case 'en_route':
                if ($customerId) {
                    $client = Client::find($customerId);
                    if ($client && $client->headquarter_latitude && $client->headquarter_longitude) {
                        $this->trackingService->setGeofenceTarget(
                            $trainerId,
                            $customerId,
                            (float) $client->headquarter_latitude,
                            (float) $client->headquarter_longitude
                        );
                    }
                }
                break;

            case 'arrived':
                $this->trackingService->clearGeofenceTarget($trainerId);
                break;

            case 'completed':
                $this->trackingService->flushTrailToDatabase($trainerId);
                $this->trackingService->cleanupTrainerKeys($trainerId);
                break;
        }
    }
}
