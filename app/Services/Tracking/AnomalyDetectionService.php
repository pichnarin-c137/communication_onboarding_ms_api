<?php

namespace App\Services\Tracking;

use App\Events\AnomalyDetected;
use App\Models\AnomalyAlert;
use App\Models\Appointment;
use App\Models\TrainerActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class AnomalyDetectionService
{
    /**
     * Run all anomaly detection checks.
     */
    public function runAllChecks(): int
    {
        $count = 0;
        $count += $this->checkTravelDelays();
        $count += $this->checkSessionOvertimes();
        $count += $this->checkDepartureWarnings();

        return $count;
    }

    /**
     * Check for GPS spoofing (called inline from TrainerTrackingService::processPing).
     */
    public function checkGpsSpoofing(string $trainerId, array $pingData): void
    {
        $accuracy = (float) ($pingData['accuracy'] ?? -1);
        $speed = isset($pingData['speed']) ? (float) $pingData['speed'] : null;
        $maxSpeedKmh = config('coms.tracking.max_speed_kmh', 200);

        $reasons = [];
        if ($accuracy == 0) {
            $reasons[] = 'accuracy_zero';
        }
        if ($speed !== null && $speed > $maxSpeedKmh) {
            $reasons[] = "speed_{$speed}_exceeds_$maxSpeedKmh";
        }

        if (empty($reasons)) {
            return;
        }

        $this->createAlert(
            $trainerId,
            null,
            'gps_spoofing',
            'high',
            [
                'reasons' => $reasons,
                'accuracy' => $accuracy,
                'speed' => $speed,
                'latitude' => $pingData['latitude'] ?? null,
                'longitude' => $pingData['longitude'] ?? null,
            ]
        );
    }

    /**
     * Check for travel delays: en_route time > factor * OSRM estimated duration.
     */
    private function checkTravelDelays(): int
    {
        $factor = config('coms.tracking.anomaly_travel_delay_factor', 2.0);
        $count = 0;

        $enRouteTrainers = Redis::hkeys(TrainerTrackingService::KEY_GEOFENCE_TARGETS) ?: [];

        foreach ($enRouteTrainers as $trainerId) {
            $statusJson = Redis::get(TrainerTrackingService::statusKey($trainerId));
            if (! $statusJson) {
                continue;
            }

            $status = json_decode($statusJson, true);
            if (($status['status'] ?? '') !== 'en_route') {
                continue;
            }

            $startedAt = isset($status['updated_at']) ? Carbon::parse($status['updated_at']) : null;
            if (! $startedAt) {
                continue;
            }

            $etaJson = Redis::get(TrainerTrackingService::etaKey($trainerId));
            if (! $etaJson) {
                continue;
            }

            $eta = json_decode($etaJson, true);
            $estimatedMinutes = $eta['eta_minutes'] ?? 0;
            if ($estimatedMinutes <= 0) {
                continue;
            }

            $elapsedMinutes = $startedAt->diffInMinutes(now());
            $threshold = $estimatedMinutes * $factor;

            if ($elapsedMinutes > $threshold) {
                $this->createAlert(
                    $trainerId,
                    $status['customer_id'] ?? null,
                    'travel_delay',
                    'high',
                    [
                        'elapsed_minutes' => $elapsedMinutes,
                        'estimated_minutes' => $estimatedMinutes,
                        'threshold_factor' => $factor,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check for session overtime: in_session time > factor * scheduled duration.
     */
    private function checkSessionOvertimes(): int
    {
        $factor = config('coms.tracking.anomaly_session_overtime_factor', 1.5);
        $count = 0;

        $inSessionLogs = TrainerActivityLog::where('status', 'in_session')
            ->whereDate('created_at', now()->toDateString())
            ->get();

        // Batch-load appointments to avoid N+1 queries
        $appointmentIds = $inSessionLogs->pluck('appointment_id')->filter()->unique()->values();
        $appointments = $appointmentIds->isNotEmpty()
            ? Appointment::whereIn('id', $appointmentIds)->get()->keyBy('id')
            : collect();

        foreach ($inSessionLogs as $log) {
            // Check if there's already a later status change (meaning session ended)
            $laterLog = TrainerActivityLog::where('trainer_id', $log->trainer_id)
                ->where('created_at', '>', $log->created_at)
                ->exists();

            if ($laterLog) {
                continue;
            }

            $elapsedMinutes = $log->created_at->diffInMinutes(now());

            // Get scheduled duration from appointment
            if ($log->appointment_id) {
                $appointment = $appointments->get($log->appointment_id);
                if ($appointment && $appointment->scheduled_start_time && $appointment->scheduled_end_time) {
                    $scheduledStart = Carbon::parse($appointment->scheduled_start_time);
                    $scheduledEnd = Carbon::parse($appointment->scheduled_end_time);
                    $scheduledMinutes = $scheduledStart->diffInMinutes($scheduledEnd);

                    if ($scheduledMinutes > 0 && $elapsedMinutes > ($scheduledMinutes * $factor)) {
                        $this->createAlert(
                            $log->trainer_id,
                            $log->customer_id,
                            'session_overtime',
                            'medium',
                            [
                                'elapsed_minutes' => $elapsedMinutes,
                                'scheduled_minutes' => $scheduledMinutes,
                                'threshold_factor' => $factor,
                                'appointment_id' => $log->appointment_id,
                            ]
                        );
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Check for departure warnings: trainer at_office with appointment in < N minutes.
     */
    private function checkDepartureWarnings(): int
    {
        $warningMinutes = config('coms.tracking.anomaly_departure_warning_minutes', 30);
        $count = 0;

        $upcomingAppointments = Appointment::where('status', 'pending')
            ->whereNotNull('trainer_id')
            ->whereDate('scheduled_date', now()->toDateString())
            ->where('location_type', '!=', 'online')
            ->get();

        foreach ($upcomingAppointments as $appointment) {
            $scheduledStart = Carbon::parse(
                $appointment->scheduled_date->toDateString().' '.$appointment->scheduled_start_time
            );

            $minutesUntilStart = now()->diffInMinutes($scheduledStart);

            if ($minutesUntilStart > 0 && $minutesUntilStart <= $warningMinutes) {
                $statusJson = Redis::get(TrainerTrackingService::statusKey($appointment->trainer_id));
                $currentStatus = 'at_office';
                if ($statusJson) {
                    $data = json_decode($statusJson, true);
                    $currentStatus = $data['status'] ?? 'at_office';
                }

                if ($currentStatus === 'at_office') {
                    $this->createAlert(
                        $appointment->trainer_id,
                        $appointment->client_id,
                        'departure_warning',
                        'low',
                        [
                            'appointment_id' => $appointment->id,
                            'scheduled_start' => $scheduledStart->setTimezone(app()->bound('request.timezone') ? app('request.timezone') : config('coms.user_settings.defaults.timezone', 'Asia/Phnom_Penh'))->format('Y-m-d\TH:i:s.uP'),
                            'minutes_until_start' => $minutesUntilStart,
                        ]
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Create an anomaly alert (idempotent: skip if unresolved alert of same type exists for trainer).
     */
    private function createAlert(
        string $trainerId,
        ?string $customerId,
        string $type,
        string $severity,
        array $details
    ): void {
        $existing = AnomalyAlert::where('trainer_id', $trainerId)
            ->where('type', $type)
            ->where('resolved', false)
            ->first();

        if ($existing) {
            return;
        }

        AnomalyAlert::create([
            'trainer_id' => $trainerId,
            'customer_id' => $customerId,
            'type' => $type,
            'severity' => $severity,
            'details' => $details,
        ]);

        try {
            AnomalyDetected::dispatch($trainerId, $type, $severity, $details);
        } catch (Throwable $e) {
            Log::error('AnomalyDetected broadcast failed', [
                'trainer_id' => $trainerId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
