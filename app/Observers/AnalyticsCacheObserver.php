<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\AppointmentFeedback;
use App\Models\OnboardingClientFeedback;
use App\Models\OnboardingRequest;
use App\Models\SaleTrainerAssignment;
use App\Services\Analytics\Support\AnalyticsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Invalidate analytics response cache when underlying entities mutate.
 * Always fails open — analytics is best-effort, never block writes.
 */
class AnalyticsCacheObserver
{
    public function __construct(private AnalyticsCache $cache) {}

    public function saved(Model $model): void
    {
        $this->flush($model);
    }

    public function deleted(Model $model): void
    {
        $this->flush($model);
    }

    private function flush(Model $model): void
    {
        try {
            $userIds = $this->affectedUsers($model);

            foreach ($userIds as $userId) {
                $this->cache->flushForUser($userId);
            }

            $this->cache->flushAdmin();
        } catch (Throwable $e) {
            Log::warning('analytics_cache.invalidate_failed', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int,string>
     */
    private function affectedUsers(Model $model): array
    {
        $ids = [];

        if ($model instanceof Appointment) {
            $ids[] = $model->trainer_id;
            $ids[] = $model->creator_id;
            if ($model->trainer_id) {
                $ids = array_merge($ids, $this->salesForTrainer($model->trainer_id));
            }
        }

        if ($model instanceof OnboardingRequest) {
            $ids[] = $model->trainer_id;
            if ($model->trainer_id) {
                $ids = array_merge($ids, $this->salesForTrainer($model->trainer_id));
            }
        }

        if ($model instanceof OnboardingClientFeedback) {
            $onboarding = $model->onboarding;
            if ($onboarding) {
                $ids[] = $onboarding->trainer_id;
                if ($onboarding->trainer_id) {
                    $ids = array_merge($ids, $this->salesForTrainer($onboarding->trainer_id));
                }
            }
        }

        if ($model instanceof AppointmentFeedback) {
            $appointment = $model->appointment;
            if ($appointment) {
                $ids[] = $appointment->trainer_id;
                $ids[] = $appointment->creator_id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return array<int,string>
     */
    private function salesForTrainer(string $trainerId): array
    {
        return SaleTrainerAssignment::query()
            ->where('trainer_user_id', $trainerId)
            ->pluck('sale_user_id')
            ->all();
    }
}
