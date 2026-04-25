<?php

namespace App\Services\Appointment;

use App\Exceptions\Business\AppointmentLockedException;
use App\Exceptions\Business\AppointmentTimeTooEarlyException;
use App\Exceptions\Business\DemoCreationForbiddenException;
use App\Exceptions\Business\OneAppointmentAtATimeException;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Media;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Services\Logging\ActivityLogger;
use App\Services\Notification\NotificationService;
use App\Services\Onboarding\OnboardingTriggerService;
use App\Services\Telegram\TelegramGroupService;
use App\Services\Tracking\EtaService;
use App\Services\Tracking\TrainerStatusService;
use App\Services\Tracking\TrainerTrackingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AppointmentService
{
    public function __construct(
        private AppointmentConflictService $conflictService,
        private AppointmentStatusService $statusService,
        private OnboardingTriggerService $onboardingTriggerService,
        private DemoCompletionService $demoCompletionService,
        private ActivityLogger $activityLogger,
        private NotificationService $notificationService,
        private TelegramGroupService $telegramGroupService,
        private TrainerStatusService $trainerStatusService,
        private EtaService $etaService,
        private TrainerTrackingService $trackingService,
        private CloudinaryService $cloudinaryService,
    ) {}

    // Read operations (cached)

    public function list(User $user, array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $cacheKey = $this->listCacheKey($user->id);
        $ttl = config('coms.cache.appointment_list_ttl', 300);

        $all = Cache::store('redis')->remember($cacheKey, $ttl, function () use ($user) {
            $role = $user->role->role ?? null;
            $query = Appointment::with([
                'trainer:id,first_name,last_name',
                'client:id,company_code,company_name',
                'creator:id,first_name,last_name',
            ]);

            if ($role === 'trainer') {
                $query->where(function ($q) use ($user) {
                    $q->where('trainer_id', $user->id)
                        ->orWhere('creator_id', $user->id);
                });
            }
            // sale and admin see all appointments

            return $query->orderByDesc('scheduled_date')->get();
        });

        // Apply in-memory filters on the cached collection
        $filtered = $all
            ->when(! empty($filters['status']), fn ($c) => $c->where('status', $filters['status']))
            ->when(! empty($filters['appointment_type']), fn ($c) => $c->where('appointment_type', $filters['appointment_type']))
            ->when(! empty($filters['scheduled_date']), fn ($c) => $c->filter(
                fn ($appt) => $appt->scheduled_date->toDateString() === $filters['scheduled_date']
            ))
            ->when(! empty($filters['client_id']), fn ($c) => $c->where('client_id', $filters['client_id']))
            ->when(! empty($filters['trainer_id']), fn ($c) => $c->where('trainer_id', $filters['trainer_id']))
            ->when(! empty($filters['creator_id']), fn ($c) => $c->where('creator_id', $filters['creator_id']))
            ->when(! empty($filters['search']), function ($c) use ($filters) {
                $search = strtolower($filters['search']);

                return $c->filter(function ($appt) use ($search) {
                    return str_contains(strtolower($appt->title), $search)
                        || str_contains(strtolower($appt->client?->company_name ?? ''), $search)
                        || str_contains(strtolower($appt->client?->company_code ?? ''), $search)
                        || str_contains(strtolower($appt->appointment_code ?? ''), $search)
                        || str_contains(strtolower($appt->trainer?->name ?? ''), $search);
                });
            })
            ->values();

        $total = $filtered->count();
        $items = $filtered->forPage($page, $perPage)->values();

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'from' => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
        ];
    }

    public function get(string $id): Appointment
    {
        $cacheKey = $this->showCacheKey($id);
        $ttl = config('coms.cache.appointment_show_ttl', 600);

        return Cache::store('redis')->remember($cacheKey, $ttl, function () use ($id) {
            return Appointment::with(['trainer', 'client', 'creator', 'students', 'materials', 'startProof', 'endProof'])
                ->findOrFail($id);
        });
    }

    // Write operations (with cache invalidation)

    public function create(array $data, string $creatorId): Appointment
    {
        $appointment = DB::transaction(function () use ($data, $creatorId) {
            $creator = User::findOrFail($creatorId);
            $creatorRole = $creator->role->role ?? null;

            if (($data['appointment_type'] ?? 'training') === 'demo' && $creatorRole !== 'sale') {
                throw new DemoCreationForbiddenException;
            }

            if (empty($data['title'])) {
                $client = isset($data['client_id']) ? Client::find($data['client_id']) : null;
                $trainerId = $creatorRole === 'trainer'
                    ? $creatorId
                    : ($data['trainer_id'] ?? null);
                $trainer = $trainerId ? User::find($trainerId) : null;
                $data['title'] = $client
                    ? "{$client->company_code} | {$client->company_name} | {$trainer->first_name} {$trainer->last_name}"
                    : (($data['appointment_type'] ?? 'training') === 'demo' ? 'Demo Session' : 'Training Session');
            }

            // Trainer creates for themselves — auto-assign and guard against active sessions
            if (empty($data['trainer_id']) && $creatorRole === 'trainer') {
                $this->ensureNoActiveAppointment($creatorId);
                $data['trainer_id'] = $creatorId;
            }

            if (! empty($data['trainer_id'])) {
                $this->conflictService->checkConflict(
                    $data['trainer_id'],
                    $data['scheduled_date'],
                    $data['scheduled_start_time'],
                    $data['scheduled_end_time']
                );
            }

            $appointment = Appointment::create(array_merge($data, [
                'creator_id' => $creatorId,
                'status' => 'pending',
                'appointment_code' => $this->generateAppointmentCode($data['appointment_type'] ?? 'training'),
            ]));

            $this->activityLogger->log(
                'appointment_created',
                "Appointment '{$appointment->title}' created",
                ['appointment_id' => $appointment->id]
            );

            return $appointment;
        });

        $this->onboardingTriggerService->handleAppointmentInProgress($appointment);

        // Cache invalidation must run after the transaction commits so that
        // any subsequent cache-miss query sees the newly inserted row.
        $this->invalidateListsFor($creatorId, $appointment->trainer_id);

        // Notify trainer only when sale explicitly assigns them — not on self-created appointments
        if (! empty($appointment->trainer_id) && $appointment->trainer_id !== $creatorId) {
            $this->notifyQuietly(
                [$appointment->trainer_id],
                'appointment_assigned',
                'New Appointment Assigned',
                "You have a new appointment '{$appointment->title}' scheduled on {$appointment->scheduled_date->format('M d, Y')}.",
                ['type' => 'appointment', 'id' => $appointment->id]
            );
        }

        // Telegram hook: notify client group when a training appointment is scheduled
        if (($appointment->appointment_type ?? 'training') === 'training') {
            $this->notifyClientTelegramQuietly($appointment, 'training_scheduled');
        }

        return $appointment;
    }

    public function update(Appointment $appt, array $data): Appointment
    {
        if ($appt->status !== 'pending') {
            throw new AppointmentLockedException;
        }

        $oldTrainerId = $appt->trainer_id;

        if (! empty($data['trainer_id']) && $data['trainer_id'] !== $appt->trainer_id) {
            $this->conflictService->checkConflict(
                $data['trainer_id'],
                $data['scheduled_date'] ?? $appt->scheduled_date->toDateString(),
                $data['scheduled_start_time'] ?? $appt->scheduled_start_time,
                $data['scheduled_end_time'] ?? $appt->scheduled_end_time,
                $appt->id
            );
        }

        $appt->update(array_filter($data, fn ($v) => ! is_null($v)));

        $this->invalidateAppointment($appt->id, $appt->creator_id, $oldTrainerId, $data['trainer_id'] ?? null);

        return $appt->fresh();
    }

    public function leaveOffice(Appointment $appt, float $lat, float $lng): void
    {
        $this->statusService->validateTransition($appt, 'leave_office');
        $this->statusService->validateLeaveOffice($appt);
        $this->ensureNoActiveAppointment($appt->trainer_id, $appt->id);

        $appt->update([
            'status' => 'leave_office',
            'leave_office_at' => now(),
            'leave_office_lat' => $lat,
            'leave_office_lng' => $lng,
        ]);

        $this->onboardingTriggerService->handleAppointmentInProgress($appt);

        $this->invalidateAppointment($appt->id, $appt->creator_id, $appt->trainer_id);

        // Sync tracking: trainer is now en_route to the client
        $this->syncTrackingStatus($appt->trainer_id, 'en_route', [
            'customer_id' => $appt->client_id,
            'appointment_id' => $appt->id,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);

        // Notify trainer only when sale explicitly assigns them — not on self-created appointments
        if (! empty($appt->creator_id) && ! empty($appt->trainer_id) && $appt->creator_id !== $appt->trainer_id) {
            $this->notifyQuietly(
                [$appt->creator_id],
                'appointment_leave_office',
                'Trainer Left Office',
                "Your trainer has left the office for appointment '{$appt->title}' on {$appt->scheduled_date->format('M d, Y')}.",
                ['type' => 'appointment', 'id' => $appt->id]
            );
        }

        $this->notifyTrainingTelegramQuietly($appt, 'training_on_the_way');
    }

    public function startAppointment(Appointment $appt, string $proofMedia, float $lat, float $lng): void
    {
        $this->statusService->validateTransition($appt, 'in_progress');
        $this->ensureNoActiveAppointment($appt->trainer_id, $appt->id);

        // Only enforce the early-start window for appointments that have NOT gone through
        // leave_office — once a trainer is physically en route, the time restriction is moot.
        if ($appt->status !== 'leave_office') {
            $scheduledStart = \Carbon\Carbon::parse(
                $appt->scheduled_date->toDateString().' '.$appt->scheduled_start_time
            );

            if (now()->lt($scheduledStart->subMinutes(30))) {
                throw new AppointmentTimeTooEarlyException;
            }
        }

        $startProofMedia = $this->handleProofMedia($proofMedia, $appt->trainer_id, 'start_proof');

        $appt->update([
            'status' => 'in_progress',
            'start_proof_media' => $startProofMedia,
            'start_lat' => $lat,
            'start_lng' => $lng,
            'actual_start_time' => now(),
        ]);

        $this->onboardingTriggerService->handleAppointmentInProgress($appt);

        $this->invalidateAppointment($appt->id, $appt->creator_id, $appt->trainer_id);

        // Sync tracking: trainer started session (skip 'arrived' — it's immediately overwritten)
        $this->syncTrackingStatus($appt->trainer_id, 'in_session', [
            'customer_id' => $appt->client_id,
            'appointment_id' => $appt->id,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);

        if (! empty($appt->creator_id) && ! empty($appt->trainer_id) && $appt->creator_id !== $appt->trainer_id) {
            $this->notifyQuietly(
                [$appt->creator_id],
                'appointment_started',
                'Appointment Started',
                "Appointment '{$appt->title}' has been started by the trainer on {$appt->scheduled_date->format('M d, Y')}.",
                ['type' => 'appointment', 'id' => $appt->id]
            );
        }

        $this->notifyTrainingTelegramQuietly($appt, 'training_started');
    }

    public function completeAppointment(
        Appointment $appt,
        string $endProofMedia,
        float $lat,
        float $lng,
        int $count,
        ?string $notes,
        ?string $completingUserId = null
    ): void {
        $this->statusService->validateTransition($appt, 'done');

        $endProofMedia = $this->handleProofMedia($endProofMedia, $appt->trainer_id ?? $completingUserId, 'complete_proof');

        $appt->update([
            'status' => 'done',
            'trainer_id' => $appt->trainer_id ?? $completingUserId,
            'end_proof_media' => $endProofMedia,
            'end_lat' => $lat,
            'end_lng' => $lng,
            'actual_end_time' => now(),
            'student_count' => $count,
            'completion_notes' => $notes,
        ]);

        $this->invalidateAppointment($appt->id, $appt->creator_id, $appt->trainer_id);

        // Sync tracking: trainer completed session
        $trainerId = $appt->trainer_id ?? $completingUserId;
        $this->syncTrackingStatus($trainerId, 'completed', [
            'customer_id' => $appt->client_id,
            'appointment_id' => $appt->id,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);

        if (! empty($appt->creator_id) && ! empty($appt->trainer_id) && $appt->creator_id !== $appt->trainer_id) {
            $this->notifyQuietly(
                [$appt->creator_id],
                'appointment_completed',
                'Appointment Completed',
                "Appointment '{$appt->title}' on {$appt->scheduled_date->format('M d, Y')} has been completed by the trainer.",
                ['type' => 'appointment', 'id' => $appt->id]
            );
        }

        // Telegram hook: notify client group when a training appointment is completed
        $this->notifyTrainingTelegramQuietly($appt, 'training_completed');

        if ($appt->appointment_type === 'training') {
            $this->onboardingTriggerService->handleAppointmentCompleted($appt);
        }

        if ($appt->appointment_type === 'demo') {
            $this->demoCompletionService->handle($appt);
        }
    }

    public function cancel(Appointment $appt, string $reason, string $userId): void
    {
        if ($appt->trainer_id === $userId && $appt->creator_id !== $userId) {
            throw new AppointmentLockedException('Trainers cannot cancel appointments assigned by sales.');
        }

        $this->statusService->validateTransition($appt, 'cancelled');

        $appt->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_by_user_id' => $userId,
            'cancelled_at' => now(),
        ]);

        $wasActive = in_array($appt->getOriginal('status'), ['leave_office', 'in_progress']);

        $this->invalidateAppointment($appt->id, $appt->creator_id, $appt->trainer_id);

        // If the appointment was active, reset the trainer's tracking state
        if ($wasActive) {
            $this->resetTrainerTracking($appt->trainer_id);
        }

        $notifyIds = array_values(array_filter(array_unique([
            ! empty($appt->creator_id) && $appt->creator_id !== $userId ? $appt->creator_id : null,
            ! empty($appt->trainer_id) && $appt->trainer_id !== $userId ? $appt->trainer_id : null,
        ])));
        if ($notifyIds) {
            $this->notifyQuietly(
                $notifyIds,
                'appointment_cancelled',
                'Appointment Cancelled',
                "Appointment '{$appt->title}' on {$appt->scheduled_date->format('M d, Y')} has been cancelled.",
                ['type' => 'appointment', 'id' => $appt->id]
            );
        }

        $this->notifyTrainingTelegramQuietly($appt, 'training_cancelled');
    }

    public function reschedule(Appointment $appt, array $newSchedule): Appointment
    {
        $wasActive = in_array($appt->status, ['leave_office', 'in_progress']);

        $newAppt = DB::transaction(function () use ($appt, $newSchedule) {
            $this->statusService->validateTransition($appt, 'rescheduled');

            if (! empty($appt->trainer_id)) {
                $this->conflictService->checkConflict(
                    $appt->trainer_id,
                    $newSchedule['scheduled_date'],
                    $newSchedule['scheduled_start_time'],
                    $newSchedule['scheduled_end_time'],
                    $appt->id
                );
            }

            $appt->update([
                'status' => 'rescheduled',
                'reschedule_reason' => $newSchedule['reschedule_reason'] ?? null,
                'reschedule_at' => now(),
                'reschedule_to_date' => $newSchedule['scheduled_date'],
                'reschedule_to_start_time' => $newSchedule['scheduled_start_time'],
                'reschedule_to_end_time' => $newSchedule['scheduled_end_time'],
            ]);

            return Appointment::create(array_merge(
                $appt->only([
                    'title',
                    'appointment_type',
                    'location_type',
                    'trainer_id',
                    'client_id',
                    'creator_id',
                    'notes',
                    'meeting_link',
                    'physical_location',
                    'is_continued_session',
                ]),
                [
                    'scheduled_date' => $newSchedule['scheduled_date'],
                    'scheduled_start_time' => $newSchedule['scheduled_start_time'],
                    'scheduled_end_time' => $newSchedule['scheduled_end_time'],
                    'status' => 'pending',
                ]
            ));
        });

        $this->invalidateAppointment($appt->id, $appt->creator_id, $appt->trainer_id);

        $this->onboardingTriggerService->handleAppointmentInProgress($newAppt);

        // If the appointment was active, reset the trainer's tracking state
        if ($wasActive) {
            $this->resetTrainerTracking($appt->trainer_id);
        }

        if (! empty($newAppt->trainer_id) && $newAppt->trainer_id !== $appt->trainer_id) {
            $this->notifyQuietly(
                [$newAppt->trainer_id],
                'appointment_rescheduled',
                'Appointment Rescheduled',
                "Appointment '{$newAppt->title}' has been rescheduled to {$newAppt->scheduled_date->format('M d, Y')}.",
                ['type' => 'appointment', 'id' => $newAppt->id]
            );
        }

        $this->notifyTrainingTelegramQuietly($newAppt, 'training_rescheduled');

        return $newAppt;
    }

    public function estimatePendingAppointments(Appointment $appt): array
    {
        if (empty($appt->trainer_id)) {
            return [];
        }

        $appt->loadMissing(['trainer.branch']);
        $trainer = $appt->trainer;
        if (! $trainer) {
            return [];
        }

        $branch = $trainer->branch;

        // Build estimate for the current appointment regardless of status.
        $current = null;
        if ($appt->location_type !== 'online') {
            $appt->loadMissing('client');
            $current = $this->buildRouteEstimate($appt, $branch);
        }

        // Gather other pending appointments for this trainer.
        $pendingAppointments = Appointment::with('client')
            ->where('trainer_id', $appt->trainer_id)
            ->where('status', 'pending')
            ->where('location_type', '!=', 'online')
            ->where('id', '!=', $appt->id)
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_start_time')
            ->get();

        $otherSuccess = [];
        $errors = [];

        foreach ($pendingAppointments as $pending) {
            $estimate = $this->buildRouteEstimate($pending, $branch);
            if (isset($estimate['reason'])) {
                $errors[] = $estimate;
            } else {
                $otherSuccess[] = $estimate;
            }
        }

        // Handle current appointment errors
        if ($current !== null && isset($current['reason'])) {
            $errors[] = $current;
            $current = null;
        }

        return [
            'trainer_position' => $this->getTrainerLivePosition($appt->trainer_id, $trainer),
            'current' => $current,
            'other_pending' => $otherSuccess,
            'errors' => $errors,
        ];
    }

    private function getTrainerLivePosition(string $trainerId, User $trainer): array
    {
        $pos = Redis::geopos(TrainerTrackingService::KEY_LOCATIONS, $trainerId);
        $statusJson = Redis::get(TrainerTrackingService::statusKey($trainerId));
        $status = $statusJson ? json_decode($statusJson, true) : [];

        $hasGps = $pos && $pos[0];
        $trainerName = trim($trainer->first_name.' '.$trainer->last_name);
        $lat = $hasGps ? (float) $pos[0][1] : null;
        $lng = $hasGps ? (float) $pos[0][0] : null;

        // Derive status by comparing trainer GPS with their branch HQ
        $computedStatus = 'offline';
        $distanceFromBranch = null;
        $branch = $trainer->branch;

        if ($hasGps && $branch && $branch->headquarters_lat && $branch->headquarters_lng) {
            $distanceFromBranch = $this->trackingService->haversineDistance(
                $lat,
                $lng,
                (float) $branch->headquarters_lat,
                (float) $branch->headquarters_lng
            );

            // Within 200m of branch HQ → at_office, otherwise → traveling
            $geofenceRadius = config('coms.tracking.geofence_radius_meters', 200);
            $computedStatus = $distanceFromBranch <= $geofenceRadius ? 'at_office' : 'traveling';
        } elseif ($hasGps) {
            $computedStatus = 'active'; // GPS available but no branch to compare
        }

        return [
            'name' => $trainerName,
            'active' => $hasGps,
            'lat' => $lat,
            'lng' => $lng,
            'status' => $computedStatus,
            'distance_from_branch_m' => $distanceFromBranch ? round($distanceFromBranch) : null,
            'branch_name' => $branch->name ?? null,
            'branch_lat' => $branch ? (float) $branch->headquarters_lat : null,
            'branch_lng' => $branch ? (float) $branch->headquarters_lng : null,
            'updated_at' => $status['updated_at'] ?? null,
        ];
    }

    private function buildRouteEstimate(Appointment $appt, ?Branch $branch): array
    {
        $client = $appt->client;
        $clientName = $client?->company_name ?? 'Unknown Client';

        $base = [
            'appointment_id' => $appt->id,
            'client_name' => $clientName,
            'scheduled_date' => $appt->scheduled_date->toDateString(),
            'scheduled_start_time' => $appt->scheduled_start_time,
        ];

        // Validate branch location
        if (! $branch || empty($branch->headquarters_lat) || empty($branch->headquarters_lng)) {
            return array_merge($base, ['reason' => 'missing_branch_location']);
        }

        // Validate client location
        if (! $client || empty($client->headquarter_latitude) || empty($client->headquarter_longitude)) {
            return array_merge($base, ['reason' => 'missing_client_location']);
        }

        $fromLat = (float) $branch->headquarters_lat;
        $fromLng = (float) $branch->headquarters_lng;
        $toLat = (float) $client->headquarter_latitude;
        $toLng = (float) $client->headquarter_longitude;

        $origin = ['lat' => $fromLat, 'lng' => $fromLng, 'label' => $branch->name];
        $destination = ['lat' => $toLat, 'lng' => $toLng, 'label' => $clientName];

        // Try cached OSRM route first (keyed by branch:client pair — static locations)
        $cacheKey = "route_estimate:{$branch->id}:{$client->id}";
        $ttl = config('coms.tracking.route_estimate_ttl', 86400);

        $cached = Cache::store('redis')->get($cacheKey);
        if ($cached) {
            return array_merge($base, $cached, [
                'origin' => $origin,
                'destination' => $destination,
                'cached' => true,
            ]);
        }

        // Call OSRM via EtaService
        $osrmResult = $this->etaService->calculateEta(
            "route_estimate_{$branch->id}_{$client->id}",
            $fromLat,
            $fromLng,
            $toLat,
            $toLng
        );

        if ($osrmResult) {
            $routeData = [
                'eta_minutes' => $osrmResult['eta_minutes'],
                'distance_meters' => $osrmResult['distance_meters'],
                'route_geometry' => $osrmResult['route_geometry'],
                'source' => 'osrm',
            ];

            Cache::store('redis')->put($cacheKey, $routeData, $ttl);

            return array_merge($base, $routeData, [
                'origin' => $origin,
                'destination' => $destination,
                'cached' => false,
            ]);
        }

        // Haversine fallback
        $distanceMeters = $this->trackingService->haversineDistance($fromLat, $fromLng, $toLat, $toLng);
        $averageSpeedKmh = config('coms.tracking.haversine_fallback_speed_kmh', 40);
        $etaMinutes = round(($distanceMeters / 1000) / $averageSpeedKmh * 60, 1);

        return array_merge($base, [
            'eta_minutes' => $etaMinutes,
            'distance_meters' => (int) $distanceMeters,
            'route_geometry' => null,
            'source' => 'haversine',
            'origin' => $origin,
            'destination' => $destination,
            'cached' => false,
        ]);
    }

    // Helpers

    private function generateAppointmentCode(string $type): string
    {
        $prefix = $type === 'demo' ? 'DEMO' : 'TRN';
        $date = now()->format('Ymd');

        for ($i = 0; $i < 10; $i++) {
            $random = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5));
            $code = "{$prefix}-{$date}-{$random}";

            if (! Appointment::where('appointment_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Failed to generate unique appointment code after 10 attempts.');
    }

    private function notifyQuietly(array $userIds, string $type, string $title, string $body, array $meta): void
    {
        try {
            $this->notificationService->notify($userIds, $type, $title, $body, $meta);
        } catch (\Throwable $e) {
            Log::error('AppointmentService notification failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyTrainingTelegramQuietly(Appointment $appt, string $messageType): void
    {
        if ($appt->appointment_type === 'training') {
            $this->notifyClientTelegramQuietly($appt, $messageType);
        }
    }

    /**
     * Send a Telegram notification to the client's connected group.
     * Failures are caught and logged — they must never propagate to break the core operation.
     */
    private function notifyClientTelegramQuietly(Appointment $appointment, string $messageType): void
    {
        try {
            $clientId = $appointment->client_id;

            if (! $clientId) {
                return;
            }

            $clientName = $appointment->client?->company_name ?? 'Client';
            $date = $appointment->scheduled_date->format('d M Y');
            $time = $appointment->scheduled_start_time;
            $trainerName = $appointment->trainer?->name ?? 'Trainer';

            $variables = match ($messageType) {
                'training_scheduled' => ['client_name' => $clientName, 'date' => $date, 'time' => $time, 'trainer_name' => $trainerName],
                'training_completed' => ['client_name' => $clientName, 'date' => $date],
                'training_started' => ['client_name' => $clientName, 'date' => $date, 'time' => $time],
                'training_on_the_way' => ['client_name' => $clientName, 'date' => $date, 'time' => $time, 'trainer_name' => $trainerName],
                'training_rescheduled' => ['client_name' => $clientName, 'date' => $date, 'time' => $time, 'reason' => $appointment->reschedule_reason ?? 'No reason provided'],
                'training_cancelled' => ['client_name' => $clientName, 'date' => $date, 'reason' => $appointment->cancellation_reason ?? 'No reason provided'],
                default => [],
            };

            $this->telegramGroupService->notifyClient($clientId, $messageType, $variables);
        } catch (\Throwable $e) {
            Log::error('AppointmentService Telegram notification failed', [
                'appointment_id' => $appointment->id,
                'message_type' => $messageType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ensure the trainer has no other active appointment (leave_office or in_progress).
     * A trainer must finish their current appointment before starting another.
     * Skipped when sale creates an appointment (trainer_id may not be set yet).
     */
    private function ensureNoActiveAppointment(?string $trainerId, ?string $excludeAppointmentId = null): void
    {
        if (! $trainerId) {
            return;
        }

        $active = Appointment::where('trainer_id', $trainerId)
            ->when($excludeAppointmentId, fn ($q) => $q->where('id', '!=', $excludeAppointmentId))
            ->whereIn('status', ['leave_office', 'in_progress'])
            ->first();

        if ($active) {
            throw new OneAppointmentAtATimeException(
                "Finish the current appointment '{$active->appointment_code}' before starting a new one.",
                context: [
                    'trainer_id' => $trainerId,
                    'active_appointment_id' => $active->id,
                    'active_appointment_status' => $active->status,
                ]
            );
        }
    }

    /**
     * Sync tracking status when appointment actions occur.
     * Failures are caught and logged — tracking must never break appointment operations.
     */
    private function syncTrackingStatus(?string $trainerId, string $trackingStatus, array $data): void
    {
        if (! $trainerId) {
            return;
        }

        try {
            $currentStatus = $this->trainerStatusService->getCurrentStatus($trainerId);
            if ($currentStatus === $trackingStatus) {
                return;
            }

            $this->trainerStatusService->changeStatus($trainerId, $trackingStatus, $data);
        } catch (\Throwable $e) {
            Log::warning('Tracking status sync failed', [
                'trainer_id' => $trainerId,
                'tracking_status' => $trackingStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset a trainer's tracking state back to at_office.
     * Used when an active appointment is cancelled or rescheduled.
     */
    private function resetTrainerTracking(?string $trainerId): void
    {
        if (! $trainerId) {
            return;
        }

        try {
            $this->trainerStatusService->resetToAtOffice($trainerId);

            Log::info('Trainer tracking reset to at_office (appointment cancelled/rescheduled)', [
                'trainer_id' => $trainerId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Trainer tracking reset failed', [
                'trainer_id' => $trainerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Helper to handle proof media.
     * Strictly treats $proof as a Base64 string, uploads to Cloudinary,
     * and returns the new Media record ID.
     */
    private function handleProofMedia(string $proof, ?string $userId, string $category): string
    {
        $cloudinaryData = $this->cloudinaryService->upload($proof, $category);

        if (! $cloudinaryData) {
            throw new \RuntimeException('Failed to upload proof to Cloudinary. Ensure valid Base64 string with Data URI prefix (e.g. data:image/png;base64,...)');
        }

        $media = Media::create([
            'filename' => basename($cloudinaryData['url']),
            'original_filename' => "proof_{$category}.png",
            'file_url' => $cloudinaryData['url'],
            'file_size' => $cloudinaryData['size'],
            'mime_type' => $cloudinaryData['mime_type'],
            'media_category' => 'other',
            'uploaded_by_user_id' => $userId,
            'cloudinary_public_id' => $cloudinaryData['public_id'],
        ]);

        return $media->id;
    }

    // Cache invalidation helpers

    private function invalidateAppointment(string $appointmentId, ?string $creatorId, ?string ...$trainerIds): void
    {
        Cache::store('redis')->forget($this->showCacheKey($appointmentId));
        $this->invalidateListsFor($creatorId, ...$trainerIds);
    }

    private function invalidateListsFor(?string ...$userIds): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            Cache::store('redis')->forget($this->listCacheKey($userId));
        }
    }

    private function listCacheKey(string $userId): string
    {
        return "appointment:list:{$userId}";
    }

    private function showCacheKey(string $id): string
    {
        return "appointment:show:{$id}";
    }
}
