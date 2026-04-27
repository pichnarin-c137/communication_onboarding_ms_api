<?php

namespace App\Services\Onboarding;

use App\Exceptions\Business\ClientFeedbackRequiredException;
use App\Exceptions\Business\DefaultPolicyCannotBeRemovedException;
use App\Exceptions\Business\InvalidStatusTransitionException;
use App\Exceptions\Business\LessonLockedAfterSendException;
use App\Exceptions\Business\OnboardingProgressTooLowException;
use App\Models\OnboardingCompanyInfo;
use App\Models\OnboardingLesson;
use App\Models\OnboardingPolicy;
use App\Models\OnboardingRequest;
use App\Models\OnboardingSystemAnalysis;
use App\Models\OnboardingTrainerAssignment;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingService
{
    public function __construct(
        private OnboardingProgressService $progressService,
        private LessonSendService $lessonSendService,
        private NotificationService $notificationService,
        private CloudinaryService $cloudinaryService,
    ) {}

    // Read operations (cached)

    public function list(User $user, array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $cacheKey = $this->listCacheKey($user->id);
        $ttl = config('coms.cache.onboarding_list_ttl', 300);

        $all = Cache::store('redis')->remember($cacheKey, $ttl, function () use ($user) {
            $role = $user->role->role ?? null;
            $query = OnboardingRequest::with([
                'client:id,company_code,company_name',
                'trainer:id,first_name,last_name',
                'appointment.creator:id,first_name,last_name',
            ]);

            if ($role === 'trainer') {
                $query->where('trainer_id', $user->id);
            } elseif ($role === 'sale') {
                $query->whereHas('appointment', fn ($q) => $q->where('creator_id', $user->id));
            }

            return $query->orderByDesc('created_at')->get();
        });

        $search = trim($filters['search'] ?? '');

        $filtered = $all
            ->when(! empty($filters['status']), fn ($c) => $c->where('status', $filters['status']))
            ->when(! empty($filters['trainer_id']), fn ($c) => $c->where('trainer_id', $filters['trainer_id']))
            ->when($search !== '', fn ($c) => $c->filter(fn ($ob) => str_contains(strtolower($ob->request_code ?? ''), strtolower($search)) ||
                str_contains(strtolower($ob->client?->company_name ?? ''), strtolower($search))
            ))
            ->when(! empty($filters['date_from']), fn ($c) => $c->filter(fn ($ob) => $ob->created_at >= $filters['date_from']
            ))
            ->when(! empty($filters['date_to']), fn ($c) => $c->filter(fn ($ob) => $ob->created_at <= $filters['date_to'].' 23:59:59'
            ))
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

    public function get(string $id): OnboardingRequest
    {
        $cacheKey = $this->showCacheKey($id);
        $ttl = config('coms.cache.onboarding_show_ttl', 600);

        return Cache::store('redis')->remember($cacheKey, $ttl, function () use ($id) {
            return OnboardingRequest::with([
                'client.sales.createdBy',
                'trainer',
                'appointment.creator',
                'companyInfo',
                'systemAnalysis',
                'policies' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
                'lessons' => fn ($q) => $q->orderBy('path')->orderBy('slot_index')->orderBy('created_at')->orderBy('id'),
            ])->findOrFail($id);
        });
    }

    public function refreshProgress(string $id): OnboardingRequest
    {
        // Always recalculate from DB — no cache read (per implementation rules)
        $onboarding = OnboardingRequest::findOrFail($id);
        $result = $this->progressService->refresh($onboarding);

        // Invalidate show cache so next read reflects updated progress
        $this->invalidateOnboarding($id, $result->trainer_id);

        return $result;
    }

    public function getClientSales(string $id): OnboardingRequest
    {
        return OnboardingRequest::with([
            'client.sales.createdBy',
            'trainer',
            'appointment.creator',
        ])->findOrFail($id);
    }

    public function start(OnboardingRequest $onboarding): void
    {
        if ($onboarding->status !== 'pending') {
            throw new InvalidStatusTransitionException(
                "Onboarding cannot be started — current status is '{$onboarding->status}'."
            );
        }

        $onboarding->update(['status' => 'in_progress']);
        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    public function complete(OnboardingRequest $onboarding, User $trainer): void
    {
        if ($onboarding->status !== 'in_progress') {
            throw new InvalidStatusTransitionException(
                "Onboarding cannot be completed — current status is '{$onboarding->status}'."
            );
        }

        $onboarding->load(['companyInfo', 'systemAnalysis', 'policies', 'lessons', 'clientFeedback']);
        $progress = $this->progressService->calculate($onboarding);
        $threshold = config('coms.onboarding_completion_threshold', 90.0);

        if ($progress < $threshold) {
            throw new OnboardingProgressTooLowException(
                "Onboarding progress is {$progress}%. At least {$threshold}% is required to mark as complete."
            );
        }

        if (! $onboarding->clientFeedback) {
            throw new ClientFeedbackRequiredException;
        }

        DB::transaction(function () use ($onboarding, $progress) {
            $onboarding->update([
                'status' => 'completed',
                'completed_at' => now(),
                'progress_percentage' => $progress,
            ]);

            try {
                $creatorId = $onboarding->appointment?->creator_id;
                if ($creatorId) {
                    $this->notificationService->notify(
                        [$creatorId],
                        'onboarding_completed',
                        'Onboarding Completed',
                        "Onboarding request {$onboarding->request_code} has been marked as completed.",
                        ['type' => 'onboarding_request', 'id' => $onboarding->id]
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('OnboardingService complete notification failed', [
                    'onboarding_id' => $onboarding->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    public function cancel(OnboardingRequest $onboarding, ?string $reason): void
    {
        if (! in_array($onboarding->status, ['pending', 'in_progress', 'on_hold', 'revision_requested'])) {
            throw new InvalidStatusTransitionException(
                "Onboarding cannot be cancelled — current status is '{$onboarding->status}'."
            );
        }

        $onboarding->update(['status' => 'cancelled']);
        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    public function hold(OnboardingRequest $onboarding, string $reason, string $userId): void
    {
        if ($onboarding->status !== 'in_progress') {
            throw new InvalidStatusTransitionException(
                "Onboarding cannot be put on hold — current status is '{$onboarding->status}'."
            );
        }

        $onboarding->update([
            'status' => 'on_hold',
            'hold_reason' => $reason,
            'hold_started_at' => now(),
            'hold_count' => $onboarding->hold_count + 1,
        ]);

        try {
            $recipients = array_unique(array_filter([
                $onboarding->appointment?->creator_id,
            ]));
            if (! empty($recipients)) {
                $this->notificationService->notify(
                    $recipients,
                    'onboarding_held',
                    'Onboarding Put On Hold',
                    "Onboarding {$onboarding->request_code} has been put on hold. Reason: {$reason}",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingService hold notification failed', [
                'onboarding_id' => $onboarding->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    public function resumeHold(OnboardingRequest $onboarding, string $userId): void
    {
        if ($onboarding->status !== 'on_hold') {
            throw new InvalidStatusTransitionException(
                "Onboarding cannot be resumed — current status is '{$onboarding->status}'."
            );
        }

        $onboarding->update([
            'status' => 'in_progress',
            'hold_reason' => null,
            'hold_started_at' => null,
        ]);

        try {
            $creatorId = $onboarding->appointment?->creator_id;
            if ($creatorId) {
                $this->notificationService->notify(
                    [$creatorId],
                    'onboarding_resumed',
                    'Onboarding Resumed',
                    "Onboarding {$onboarding->request_code} has been resumed.",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingService resumeHold notification failed', [
                'onboarding_id' => $onboarding->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    public function requestRevision(OnboardingRequest $onboarding, string $note, string $userId): void
    {
        if ($onboarding->status !== 'in_progress') {
            throw new InvalidStatusTransitionException(
                "Revision can only be requested when onboarding is in progress — current status is '{$onboarding->status}'."
            );
        }

        $now = now();

        $onboarding->update([
            'status' => 'revision_requested',
            'revision_note' => $note,
        ]);

        \App\Models\OnboardingRevisionHistory::create([
            'onboarding_id' => $onboarding->id,
            'note' => $note,
            'requested_by_user_id' => $userId,
            'requested_at' => $now,
        ]);

        try {
            if ($onboarding->trainer_id) {
                $this->notificationService->notify(
                    [$onboarding->trainer_id],
                    'onboarding_revision_requested',
                    'Revision Requested',
                    "A revision has been requested for onboarding {$onboarding->request_code}. Note: {$note}",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingService requestRevision notification failed', [
                'onboarding_id' => $onboarding->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    public function acknowledgeRevision(OnboardingRequest $onboarding, string $userId): void
    {
        if ($onboarding->status !== 'revision_requested') {
            throw new InvalidStatusTransitionException(
                "Cannot acknowledge revision — current status is '{$onboarding->status}'."
            );
        }

        // revision_note is preserved as per design
        $onboarding->update([
            'status' => 'in_progress',
        ]);

        $latestRevision = \App\Models\OnboardingRevisionHistory::where('onboarding_id', $onboarding->id)
            ->whereNull('acknowledged_at')
            ->orderByDesc('requested_at')
            ->first();

        $latestRevision?->update([
            'acknowledged_by_user_id' => $userId,
            'acknowledged_at' => now(),
        ]);

        try {
            $requesterId = $latestRevision?->requested_by_user_id;
            if ($requesterId) {
                $this->notificationService->notify(
                    [$requesterId],
                    'onboarding_revision_acknowledged',
                    'Revision Acknowledged',
                    "The trainer has acknowledged the revision request for onboarding {$onboarding->request_code}.",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingService acknowledgeRevision notification failed', [
                'onboarding_id' => $onboarding->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    public function reopen(OnboardingRequest $onboarding, string $userId): void
    {
        if ($onboarding->status !== 'cancelled') {
            throw new InvalidStatusTransitionException(
                "Onboarding cannot be reopened — current status is '{$onboarding->status}'."
            );
        }

        $onboarding->update([
            'status' => 'in_progress',
            'reopened_at' => now(),
            'reopened_by_user_id' => $userId,
        ]);

        try {
            if ($onboarding->trainer_id) {
                $this->notificationService->notify(
                    [$onboarding->trainer_id],
                    'onboarding_reopened',
                    'Onboarding Reopened',
                    "Onboarding {$onboarding->request_code} has been reopened. Please resume your work.",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingService reopen notification failed', [
                'onboarding_id' => $onboarding->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    public function reassignTrainer(OnboardingRequest $onboarding, string $newTrainerId, string $adminId, ?string $notes): void
    {
        $newTrainer = User::with('role')->findOrFail($newTrainerId);

        if (($newTrainer->role->role ?? null) !== 'trainer') {
            throw new InvalidStatusTransitionException(
                'The selected user is not a trainer.'
            );
        }

        $oldTrainerId = $onboarding->trainer_id;

        DB::transaction(function () use ($onboarding, $newTrainerId, $adminId, $notes) {
            // Close current assignment
            OnboardingTrainerAssignment::where('onboarding_id', $onboarding->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'replaced_at' => now(),
                ]);

            // Insert new assignment
            OnboardingTrainerAssignment::create([
                'onboarding_id' => $onboarding->id,
                'trainer_id' => $newTrainerId,
                'assigned_by_id' => $adminId,
                'assigned_at' => now(),
                'is_current' => true,
                'notes' => $notes,
            ]);

            // Update authoritative trainer_id
            $onboarding->update(['trainer_id' => $newTrainerId]);
        });

        try {
            if ($oldTrainerId && $oldTrainerId !== $newTrainerId) {
                $this->notificationService->notify(
                    [$oldTrainerId],
                    'onboarding_trainer_reassigned',
                    'Onboarding Reassigned',
                    "You have been removed from onboarding {$onboarding->request_code}.",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );
            }

            $this->notificationService->notify(
                [$newTrainerId],
                'onboarding_trainer_reassigned',
                'Onboarding Assigned',
                "You have been assigned to onboarding {$onboarding->request_code}.",
                ['type' => 'onboarding_request', 'id' => $onboarding->id]
            );
        } catch (\Throwable $e) {
            Log::error('OnboardingService reassignTrainer notification failed', [
                'onboarding_id' => $onboarding->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Invalidate both trainers' caches
        if ($oldTrainerId) {
            Cache::store('redis')->forget("onboarding:list:{$oldTrainerId}");
        }
        Cache::store('redis')->forget("onboarding:list:{$newTrainerId}");
        $this->invalidateOnboarding($onboarding->id, $newTrainerId);
    }

    public function setDueDate(OnboardingRequest $onboarding, string $dueDate, string $userId): void
    {
        if (in_array($onboarding->status, ['completed', 'cancelled'])) {
            throw new InvalidStatusTransitionException(
                "Due date cannot be set — onboarding is '{$onboarding->status}'."
            );
        }

        $parsed = \Carbon\Carbon::parse($dueDate);

        $onboarding->update(['due_date' => $parsed->toDateString()]);

        try {
            if ($onboarding->trainer_id) {
                $this->notificationService->notify(
                    [$onboarding->trainer_id],
                    'onboarding_due_date_set',
                    'Due Date Updated',
                    "The due date for onboarding {$onboarding->request_code} has been set to {$parsed->toDateString()}.",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingService setDueDate notification failed', [
                'onboarding_id' => $onboarding->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    public function listLinkedAppointments(OnboardingRequest $onboarding): Collection
    {
        return $onboarding->linkedAppointments()
            ->with([
                'appointment:id,title,appointment_type,status,scheduled_date,scheduled_start_time,scheduled_end_time,trainer_id,creator_id',
                'appointment.trainer:id,first_name,last_name',
                'appointment.creator:id,first_name,last_name',
            ])
            ->orderBy('linked_at')
            ->get()
            ->map(fn ($link) => [
                'id' => $link->id,
                'appointment_id' => $link->appointment_id,
                'session_type' => $link->session_type,
                'linked_at' => $link->linked_at,
                'appointment' => $link->appointment ? [
                    'id' => $link->appointment->id,
                    'title' => $link->appointment->title,
                    'appointment_type' => $link->appointment->appointment_type,
                    'status' => $link->appointment->status,
                    'scheduled_date' => $link->appointment->scheduled_date,
                    'scheduled_start_time' => $link->appointment->scheduled_start_time,
                    'scheduled_end_time' => $link->appointment->scheduled_end_time,
                    'trainer' => $link->appointment->trainer
                        ? ['id' => $link->appointment->trainer->id, 'first_name' => $link->appointment->trainer->first_name, 'last_name' => $link->appointment->trainer->last_name]
                        : null,
                    'creator' => $link->appointment->creator
                        ? ['id' => $link->appointment->creator->id, 'first_name' => $link->appointment->creator->first_name, 'last_name' => $link->appointment->creator->last_name]
                        : null,
                ] : null,
            ]);
    }

    public function getCycles(OnboardingRequest $onboarding): Collection
    {
        return OnboardingRequest::where('client_id', $onboarding->client_id)
            ->with('trainer')
            ->orderBy('cycle_number')
            ->get()
            ->map(fn ($ob) => [
                'id' => $ob->id,
                'request_code' => $ob->request_code,
                'cycle_number' => $ob->cycle_number,
                'status' => $ob->status,
                'trainer_name' => $ob->trainer ? ($ob->trainer->first_name.' '.$ob->trainer->last_name) : null,
                'progress_percentage' => $ob->progress_percentage,
                'completed_at' => $ob->completed_at,
                'created_at' => $ob->created_at,
            ]);
    }

    // Company Info

    public function getCompanyInfo(OnboardingRequest $onboarding): OnboardingCompanyInfo
    {
        return $onboarding->companyInfo ?? OnboardingCompanyInfo::firstOrCreate(
            ['onboarding_id' => $onboarding->id],
            ['content' => null, 'is_completed' => false]
        );
    }

    public function updateCompanyInfo(OnboardingCompanyInfo $info, array $data, string $userId): OnboardingCompanyInfo
    {
        // Decode the incoming content JSON (company name, phone, etc.) into an array
        $contentArray = json_decode($data['content'] ?? '{}', true) ?? [];

        // Upload logo / patent images to Cloudinary if Base64 data URIs were provided
        $this->uploadBase64ToContent($contentArray, $data['logo_base64'] ?? null, 'logos', 'logo_url', $info->onboarding_id);
        $this->uploadBase64ToContent($contentArray, $data['patent_document_base64'] ?? null, 'patents', 'patent_image_url', $info->onboarding_id);

        $updateData = ['content' => json_encode($contentArray, JSON_UNESCAPED_UNICODE)];

        if (isset($data['is_completed'])) {
            $updateData['is_completed'] = $data['is_completed'];
            if ($data['is_completed']) {
                $updateData['completed_at'] = now();
                $updateData['completed_by_user_id'] = $userId;
            }
        }

        $info->update($updateData);
        $this->invalidateOnboarding($info->onboarding_id, $info->onboarding?->trainer_id);

        return $info;
    }

    // System Analysis

    public function getSystemAnalysis(OnboardingRequest $onboarding): OnboardingSystemAnalysis
    {
        return $onboarding->systemAnalysis ?? OnboardingSystemAnalysis::firstOrCreate(
            ['onboarding_id' => $onboarding->id],
            ['import_employee_count' => 0, 'connected_app_count' => 0, 'profile_mobile_count' => 0]
        );
    }

    public function updateSystemAnalysis(OnboardingSystemAnalysis $analysis, array $data): OnboardingSystemAnalysis
    {
        $analysis->update(array_filter($data, fn ($v) => ! is_null($v)));
        $this->invalidateOnboarding($analysis->onboarding_id, $analysis->onboarding?->trainer_id);

        return $analysis;
    }

    // Policies

    public function listPolicies(OnboardingRequest $onboarding): Collection
    {
        return $onboarding->policies()->orderBy('created_at')->get();
    }

    public function addPolicy(OnboardingRequest $onboarding, string $policyName): OnboardingPolicy
    {
        $policy = OnboardingPolicy::create([
            'onboarding_id' => $onboarding->id,
            'policy_name' => $policyName,
            'is_default' => false,
            'is_checked' => false,
        ]);

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);

        return $policy;
    }

    public function checkPolicy(OnboardingPolicy $policy, string $userId): OnboardingPolicy
    {
        $policy->update([
            'is_checked' => true,
            'checked_at' => now(),
            'checked_by_user_id' => $userId,
        ]);

        $this->invalidateOnboarding($policy->onboarding_id, $policy->onboarding?->trainer_id);

        return $policy->fresh();
    }

    public function uncheckPolicy(OnboardingPolicy $policy, string $userId): OnboardingPolicy
    {
        $policy->update([
            'is_checked' => false,
            'unchecked_at' => now(),
            'unchecked_by_user_id' => $userId,
        ]);

        $this->invalidateOnboarding($policy->onboarding_id, $policy->onboarding?->trainer_id);

        return $policy->fresh();
    }

    public function removePolicy(OnboardingPolicy $policy): void
    {
        if ($policy->is_default) {
            throw new DefaultPolicyCannotBeRemovedException;
        }

        $onboardingId = $policy->onboarding_id;
        $trainerId = $policy->onboarding?->trainer_id;

        $policy->delete();

        $this->invalidateOnboarding($onboardingId, $trainerId);
    }

    // Lessons

    public function listLessons(OnboardingRequest $onboarding): Collection
    {
        return $onboarding->lessons()
            ->orderBy('path')
            ->orderBy('slot_index')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function addLesson(OnboardingRequest $onboarding, array $data): OnboardingLesson
    {
        $slotIndex = isset($data['slot_index'])
            ? max(1, (int) $data['slot_index'])
            : $this->nextLessonSlotIndex($onboarding->id, (int) $data['path']);

        $lesson = OnboardingLesson::create(array_merge($data, [
            'onboarding_id' => $onboarding->id,
            'slot_index' => $slotIndex,
            'is_sent' => false,
        ]));

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);

        return $lesson;
    }

    public function updateLesson(OnboardingLesson $lesson, array $data): OnboardingLesson
    {
        if ($lesson->is_sent) {
            throw new LessonLockedAfterSendException;
        }

        $lesson->update(array_filter($data, fn ($v) => ! is_null($v)));
        $this->invalidateOnboarding($lesson->onboarding_id, $lesson->onboarding?->trainer_id);

        return $lesson->fresh();
    }

    public function deleteLesson(OnboardingLesson $lesson): void
    {
        if ($lesson->is_sent) {
            throw new LessonLockedAfterSendException;
        }

        $onboardingId = $lesson->onboarding_id;
        $trainerId = $lesson->onboarding?->trainer_id;

        $lesson->delete();

        $this->invalidateOnboarding($onboardingId, $trainerId);
    }

    public function sendLesson(OnboardingLesson $lesson, string $userId): void
    {
        $this->lessonSendService->send($lesson, $userId);
        $this->invalidateOnboarding($lesson->onboarding_id, $lesson->onboarding?->trainer_id);
    }

    // Private helpers

    private function uploadBase64ToContent(array &$contentArray, ?string $base64, string $category, string $urlKey, string $onboardingId): void
    {
        if (empty($base64)) {
            return;
        }

        $result = $this->cloudinaryService->upload($base64, $category);
        if ($result) {
            $contentArray[$urlKey] = $result['url'];
        } else {
            Log::warning("OnboardingService: {$category} Cloudinary upload failed.", [
                'onboarding_id' => $onboardingId,
            ]);
        }
    }

    private function nextLessonSlotIndex(string $onboardingId, int $path): int
    {
        $maxSlot = OnboardingLesson::where('onboarding_id', $onboardingId)
            ->where('path', $path)
            ->max('slot_index');

        return ((int) $maxSlot) + 1;
    }

    // Cache invalidation helpers

    public function invalidateOnboarding(string $onboardingId, ?string $trainerId = null): void
    {
        Cache::store('redis')->forget($this->showCacheKey($onboardingId));
        Cache::store('redis')->forget("onboarding:progress:{$onboardingId}");

        if ($trainerId) {
            Cache::store('redis')->forget($this->listCacheKey($trainerId));
        }
    }

    private function listCacheKey(string $userId): string
    {
        return "onboarding:list:{$userId}";
    }

    private function showCacheKey(string $id): string
    {
        return "onboarding:show:{$id}";
    }
}
