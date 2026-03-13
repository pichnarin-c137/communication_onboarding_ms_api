<?php

namespace App\Services\Onboarding;

use App\Exceptions\Business\DefaultPolicyCannotBeRemovedException;
use App\Exceptions\Business\InvalidStatusTransitionException;
use App\Exceptions\Business\LessonLockedAfterSendException;
use App\Exceptions\Business\OnboardingProgressTooLowException;
use App\Models\OnboardingCompanyInfo;
use App\Models\OnboardingLesson;
use App\Models\OnboardingPolicy;
use App\Models\OnboardingRequest;
use App\Models\OnboardingSystemAnalysis;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    public function __construct(
        private OnboardingProgressService $progressService,
        private LessonSendService $lessonSendService,
        private NotificationService $notificationService
    ) {}

    // -------------------------------------------------------------------------
    // Read operations (cached)
    // -------------------------------------------------------------------------

    public function list(User $user, array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $cacheKey = $this->listCacheKey($user->id);
        $ttl      = config('coms.cache.onboarding_list_ttl', 300);

        $all = Cache::store('redis')->remember($cacheKey, $ttl, function () use ($user) {
            $role  = $user->role->role ?? null;
            $query = OnboardingRequest::with(['client', 'trainer', 'appointment']);

            if ($role === 'trainer') {
                $query->where('trainer_id', $user->id);
            } elseif ($role === 'sale') {
                $query->whereHas('appointment', fn ($q) => $q->where('creator_id', $user->id));
            }

            return $query->orderByDesc('created_at')->get();
        });

        $filtered = $all
            ->when(! empty($filters['status']), fn ($c) => $c->where('status', $filters['status']))
            ->values();

        $total = $filtered->count();
        $items = $filtered->forPage($page, $perPage)->values();

        return [
            'data' => $items,
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
                'from'         => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
                'to'           => min($page * $perPage, $total),
            ],
        ];
    }

    public function get(string $id): OnboardingRequest
    {
        $cacheKey = $this->showCacheKey($id);
        $ttl      = config('coms.cache.onboarding_show_ttl', 600);

        return Cache::store('redis')->remember($cacheKey, $ttl, function () use ($id) {
            return OnboardingRequest::with([
                'client', 'trainer', 'appointment',
                'companyInfo', 'systemAnalysis', 'policies', 'lessons',
            ])->findOrFail($id);
        });
    }

    public function refreshProgress(string $id): OnboardingRequest
    {
        // Always recalculate from DB — no cache read (per implementation rules)
        $onboarding = OnboardingRequest::findOrFail($id);
        $result     = $this->progressService->refresh($onboarding);

        // Invalidate show cache so next read reflects updated progress
        $this->invalidateOnboarding($id, $result->trainer_id);

        return $result;
    }

    public function complete(OnboardingRequest $onboarding, User $trainer): void
    {
        if ($onboarding->status !== 'in_progress') {
            throw new InvalidStatusTransitionException(
                "Onboarding cannot be completed — current status is '{$onboarding->status}'."
            );
        }

        $onboarding->load(['companyInfo', 'systemAnalysis', 'policies', 'lessons']);
        $progress  = $this->progressService->calculate($onboarding);
        $threshold = config('coms.onboarding_completion_threshold', 90.0);

        if ($progress < $threshold) {
            throw new OnboardingProgressTooLowException(
                "Onboarding progress is {$progress}%. At least {$threshold}% is required to mark as complete."
            );
        }

        DB::transaction(function () use ($onboarding, $progress) {
            $onboarding->update([
                'status'              => 'completed',
                'completed_at'        => now(),
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
                    'error'         => $e->getMessage(),
                ]);
            }
        });

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    public function cancel(OnboardingRequest $onboarding, ?string $reason): void
    {
        if ($onboarding->status !== 'in_progress') {
            throw new InvalidStatusTransitionException(
                "Onboarding cannot be cancelled — current status is '{$onboarding->status}'."
            );
        }

        $onboarding->update(['status' => 'cancelled']);
        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);
    }

    // -------------------------------------------------------------------------
    // Company Info
    // -------------------------------------------------------------------------

    public function getCompanyInfo(OnboardingRequest $onboarding): OnboardingCompanyInfo
    {
        return $onboarding->companyInfo ?? OnboardingCompanyInfo::firstOrCreate(
            ['onboarding_id' => $onboarding->id],
            ['content' => null, 'is_completed' => false]
        );
    }

    public function updateCompanyInfo(OnboardingCompanyInfo $info, array $data, string $userId): OnboardingCompanyInfo
    {
        $updateData = array_filter($data, fn ($v) => ! is_null($v));

        if (! empty($data['is_completed'])) {
            $updateData['completed_at']          = now();
            $updateData['completed_by_user_id']  = $userId;
        }

        $info->update($updateData);
        $this->invalidateOnboarding($info->onboarding_id, $info->onboarding?->trainer_id);

        return $info->fresh();
    }

    // -------------------------------------------------------------------------
    // System Analysis
    // -------------------------------------------------------------------------

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

        return $analysis->fresh();
    }

    // -------------------------------------------------------------------------
    // Policies
    // -------------------------------------------------------------------------

    public function listPolicies(OnboardingRequest $onboarding): Collection
    {
        return $onboarding->policies()->orderBy('created_at')->get();
    }

    public function addPolicy(OnboardingRequest $onboarding, string $policyName): OnboardingPolicy
    {
        $policy = OnboardingPolicy::create([
            'onboarding_id' => $onboarding->id,
            'policy_name'   => $policyName,
            'is_default'    => false,
            'is_checked'    => false,
        ]);

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);

        return $policy;
    }

    public function checkPolicy(OnboardingPolicy $policy, string $userId): OnboardingPolicy
    {
        $policy->update([
            'is_checked'         => true,
            'checked_at'         => now(),
            'checked_by_user_id' => $userId,
        ]);

        $this->invalidateOnboarding($policy->onboarding_id, $policy->onboarding?->trainer_id);

        return $policy->fresh();
    }

    public function removePolicy(OnboardingPolicy $policy): void
    {
        if ($policy->is_default) {
            throw new DefaultPolicyCannotBeRemovedException();
        }

        $onboardingId = $policy->onboarding_id;
        $trainerId    = $policy->onboarding?->trainer_id;

        $policy->delete();

        $this->invalidateOnboarding($onboardingId, $trainerId);
    }

    // -------------------------------------------------------------------------
    // Lessons
    // -------------------------------------------------------------------------

    public function listLessons(OnboardingRequest $onboarding): Collection
    {
        return $onboarding->lessons()->orderBy('created_at')->get();
    }

    public function addLesson(OnboardingRequest $onboarding, array $data): OnboardingLesson
    {
        $lesson = OnboardingLesson::create(array_merge($data, [
            'onboarding_id' => $onboarding->id,
            'is_sent'       => false,
        ]));

        $this->invalidateOnboarding($onboarding->id, $onboarding->trainer_id);

        return $lesson;
    }

    public function updateLesson(OnboardingLesson $lesson, array $data): OnboardingLesson
    {
        if ($lesson->is_sent) {
            throw new LessonLockedAfterSendException();
        }

        $lesson->update(array_filter($data, fn ($v) => ! is_null($v)));
        $this->invalidateOnboarding($lesson->onboarding_id, $lesson->onboarding?->trainer_id);

        return $lesson->fresh();
    }

    public function deleteLesson(OnboardingLesson $lesson): void
    {
        if ($lesson->is_sent) {
            throw new LessonLockedAfterSendException();
        }

        $onboardingId = $lesson->onboarding_id;
        $trainerId    = $lesson->onboarding?->trainer_id;

        $lesson->delete();

        $this->invalidateOnboarding($onboardingId, $trainerId);
    }

    public function sendLesson(OnboardingLesson $lesson, string $userId): void
    {
        $this->lessonSendService->send($lesson, $userId);
        $this->invalidateOnboarding($lesson->onboarding_id, $lesson->onboarding?->trainer_id);
    }

    // -------------------------------------------------------------------------
    // Cache invalidation helpers
    // -------------------------------------------------------------------------

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
