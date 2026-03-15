<?php

namespace App\Services\Appointment;

use App\Exceptions\Business\AppointmentLockedException;
use App\Exceptions\Business\AppointmentTimeTooEarlyException;
use App\Exceptions\Business\DemoCreationForbiddenException;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Logging\ActivityLogger;
use App\Services\Notification\NotificationService;
use App\Services\Onboarding\OnboardingTriggerService;
use App\Services\Telegram\TelegramGroupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    ) {}

    // Read operations (cached)

    public function list(User $user, array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $cacheKey = $this->listCacheKey($user->id);
        $ttl = config('coms.cache.appointment_list_ttl', 300);

        $all = Cache::store('redis')->remember($cacheKey, $ttl, function () use ($user) {
            $role = $user->role->role ?? null;
            $query = Appointment::with(['trainer', 'client', 'creator']);

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
            ->when(! empty($filters['scheduled_date']), fn ($c) => $c->where('scheduled_date', $filters['scheduled_date']))
            ->when(! empty($filters['client_id']), fn ($c) => $c->where('client_id', $filters['client_id']))
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
            return Appointment::with(['trainer', 'client', 'creator', 'students', 'materials'])
                ->findOrFail($id);
        });
    }

    // Write operations (with cache invalidation)

    public function create(array $data, string $creatorId): Appointment
    {
        $appointment = DB::transaction(function () use ($data, $creatorId) {
            if (empty($data['title'])) {
                $data['title'] = 'Training Appointment';
            }

            $creator = User::findOrFail($creatorId);
            $creatorRole = $creator->role->role ?? null;

            if (($data['appointment_type'] ?? 'training') === 'demo' && $creatorRole !== 'sale') {
                throw new DemoCreationForbiddenException;
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
            ]));

            $this->activityLogger->log(
                'appointment_created',
                "Appointment '{$appointment->title}' created",
                ['appointment_id' => $appointment->id]
            );

            return $appointment;
        });

        // Cache invalidation must run after the transaction commits so that
        // any subsequent cache-miss query sees the newly inserted row.
        $this->invalidateListsFor($creatorId, $data['trainer_id'] ?? null);

        if (! empty($appointment->trainer_id)) {
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

        $appt->update([
            'status' => 'leave_office',
            'leave_office_at' => now(),
            'leave_office_lat' => $lat,
            'leave_office_lng' => $lng,
        ]);

        $this->invalidateAppointment($appt->id, $appt->creator_id, $appt->trainer_id);

        if ($appt->creator_id) {
            $this->notifyQuietly(
                [$appt->creator_id],
                'appointment_leave_office',
                'Trainer Left Office',
                "Your trainer has left the office for appointment '{$appt->title}' on {$appt->scheduled_date->format('M d, Y')}.",
                ['type' => 'appointment', 'id' => $appt->id]
            );
        }
    }

    public function startAppointment(Appointment $appt, string $proofMediaId, float $lat, float $lng): void
    {
        $this->statusService->validateTransition($appt, 'in_progress');

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

        $appt->update([
            'status' => 'in_progress',
            'start_proof_media_id' => $proofMediaId,
            'start_lat' => $lat,
            'start_lng' => $lng,
            'actual_start_time' => now(),
        ]);

        $this->invalidateAppointment($appt->id, $appt->creator_id, $appt->trainer_id);

        if ($appt->creator_id) {
            $this->notifyQuietly(
                [$appt->creator_id],
                'appointment_started',
                'Appointment Started',
                "Appointment '{$appt->title}' has been started by the trainer on {$appt->scheduled_date->format('M d, Y')}.",
                ['type' => 'appointment', 'id' => $appt->id]
            );
        }
    }

    public function completeAppointment(
        Appointment $appt,
        string $proofMediaId,
        float $lat,
        float $lng,
        int $count,
        ?string $notes,
        ?string $completingUserId = null
    ): void {
        $this->statusService->validateTransition($appt, 'done');

        $appt->update([
            'status' => 'done',
            'trainer_id' => $appt->trainer_id ?? $completingUserId,
            'end_proof_media_id' => $proofMediaId,
            'end_lat' => $lat,
            'end_lng' => $lng,
            'actual_end_time' => now(),
            'student_count' => $count,
            'completion_notes' => $notes,
        ]);

        $this->invalidateAppointment($appt->id, $appt->creator_id, $appt->trainer_id);

        if ($appt->creator_id) {
            $this->notifyQuietly(
                [$appt->creator_id],
                'appointment_completed',
                'Appointment Completed',
                "Appointment '{$appt->title}' on {$appt->scheduled_date->format('M d, Y')} has been completed by the trainer.",
                ['type' => 'appointment', 'id' => $appt->id]
            );
        }

        $fresh = $appt->fresh();

        // Telegram hook: notify client group when a training appointment is completed
        if ($fresh->appointment_type === 'training') {
            $this->notifyClientTelegramQuietly($fresh, 'training_completed');
        }

        if ($fresh->appointment_type === 'training' && ! $fresh->is_onboarding_triggered && ! $fresh->is_continued_session) {
            $this->onboardingTriggerService->trigger($fresh);
        }

        if ($fresh->appointment_type === 'demo') {
            $this->demoCompletionService->handle($fresh);
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

        $this->invalidateAppointment($appt->id, $appt->creator_id, $appt->trainer_id);

        $notifyIds = array_values(array_filter(array_unique([
            $appt->creator_id !== $userId ? $appt->creator_id : null,
            $appt->trainer_id && $appt->trainer_id !== $userId ? $appt->trainer_id : null,
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
    }

    public function reschedule(Appointment $appt, array $newSchedule): Appointment
    {
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
            ]);

            return Appointment::create(array_merge(
                $appt->only([
                    'title', 'appointment_type', 'location_type',
                    'trainer_id', 'client_id', 'creator_id', 'notes', 'meeting_link',
                    'physical_location', 'is_continued_session',
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

        if (! empty($newAppt->trainer_id)) {
            $this->notifyQuietly(
                [$newAppt->trainer_id],
                'appointment_rescheduled',
                'Appointment Rescheduled',
                "Appointment '{$newAppt->title}' has been rescheduled to {$newAppt->scheduled_date->format('M d, Y')}.",
                ['type' => 'appointment', 'id' => $newAppt->id]
            );
        }

        return $newAppt;
    }

    // Helpers

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

            $client  = $appointment->client;
            $trainer = $appointment->trainer;

            $variables = match ($messageType) {
                'training_scheduled' => [
                    'client_name'  => $client?->company_name ?? 'Client',
                    'date'         => $appointment->scheduled_date->format('d M Y'),
                    'time'         => $appointment->scheduled_start_time,
                    'trainer_name' => $trainer?->name ?? 'Trainer',
                ],
                'training_completed' => [
                    'client_name' => $client?->company_name ?? 'Client',
                    'date'        => $appointment->scheduled_date->format('d M Y'),
                ],
                default => [],
            };

            $this->telegramGroupService->notifyClient($clientId, $messageType, $variables);
        } catch (\Throwable $e) {
            Log::error('AppointmentService Telegram notification failed', [
                'appointment_id' => $appointment->id,
                'message_type'   => $messageType,
                'error'          => $e->getMessage(),
            ]);
        }
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
