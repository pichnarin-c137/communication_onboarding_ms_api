<?php

namespace App\Services\Analytics\Support;

use App\Models\OnboardingRequest;
use App\Models\OnboardingTrainerAssignment;
use Carbon\CarbonInterface;

/**
 * Resolve which trainer "owned" an onboarding at a specific moment in time.
 *
 * Used when attributing onboarding feedback to a trainer for per-trainer KPIs:
 * the trainer that was current at submission time gets credit, not whoever
 * happens to be on the onboarding_requests row right now.
 */
class TrainerAttribution
{
    /**
     * @param  array<string>  $onboardingIds
     * @return array<string,string|null>  onboardingId → trainerUserId|null
     */
    public function bulkResolve(array $onboardingIds, CarbonInterface $cutoff): array
    {
        if (empty($onboardingIds)) {
            return [];
        }

        $assignments = OnboardingTrainerAssignment::query()
            ->whereIn('onboarding_id', $onboardingIds)
            ->where('assigned_at', '<=', $cutoff)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('replaced_at')->orWhere('replaced_at', '>', $cutoff);
            })
            ->orderByDesc('assigned_at')
            ->get(['onboarding_id', 'trainer_id', 'assigned_at']);

        $out = [];
        foreach ($assignments as $row) {
            if (! isset($out[$row->onboarding_id])) {
                $out[$row->onboarding_id] = $row->trainer_id;
            }
        }

        // Fallback for onboardings that have no assignment row at/before the cutoff:
        // use the current onboarding_requests.trainer_id.
        $missing = array_diff($onboardingIds, array_keys($out));
        if (! empty($missing)) {
            $fallback = OnboardingRequest::query()
                ->whereIn('id', $missing)
                ->pluck('trainer_id', 'id')
                ->all();

            foreach ($missing as $id) {
                $out[$id] = $fallback[$id] ?? null;
            }
        }

        return $out;
    }
}
