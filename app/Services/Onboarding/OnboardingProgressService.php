<?php

namespace App\Services\Onboarding;

use App\Models\OnboardingRequest;

class OnboardingProgressService
{
    public function calculate(OnboardingRequest $onboarding): float
    {
        $total = 0;
        $completed = 0;

        // Company info (1 task)
        $total += 1;
        $companyInfo = $onboarding->companyInfo;
        if ($companyInfo && $companyInfo->is_completed) {
            $completed += 1;
        }

        // System analysis (3 sub-tasks: each count > 0)
        $total += 3;
        $analysis = $onboarding->systemAnalysis;
        if ($analysis) {
            if ($analysis->import_employee_count > 0) {
                $completed += 1;
            }
            if ($analysis->connected_app_count > 0) {
                $completed += 1;
            }
            if ($analysis->profile_mobile_count > 0) {
                $completed += 1;
            }
        }

        // Policies
        $policies = $onboarding->policies;
        $total += $policies->count();
        $completed += $policies->where('is_checked', true)->count();

        // Lessons
        $lessons = $onboarding->lessons;
        $total += $lessons->count();
        $completed += $lessons->where('is_sent', true)->count();

        if ($total === 0) {
            return 0.0;
        }

        return round(($completed / $total) * 100, 2);
    }

    public function refresh(OnboardingRequest $onboarding): OnboardingRequest
    {
        $onboarding->load(['companyInfo', 'systemAnalysis', 'policies', 'lessons']);
        $percentage = $this->calculate($onboarding);

        $onboarding->update(['progress_percentage' => $percentage]);

        return $onboarding->fresh();
    }
}
