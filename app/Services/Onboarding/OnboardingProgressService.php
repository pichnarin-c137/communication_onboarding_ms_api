<?php

namespace App\Services\Onboarding;

use App\Models\OnboardingRequest;
use App\Services\Telegram\TelegramGroupService;
use Illuminate\Support\Facades\Log;

class OnboardingProgressService
{
    public function __construct(
        private TelegramGroupService $telegramGroupService,
    ) {}

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

    /**
     * Fire a Telegram notification when an onboarding step is completed.
     * Failures are caught and logged — they must never break the core operation.
     *
     * @param  OnboardingRequest  $onboarding   The onboarding request the step belongs to
     * @param  string             $stepName     Human-readable step name (e.g. "Company Information")
     * @param  float              $progress     Current progress percentage (0–100)
     */
    public function notifyStepCompleted(OnboardingRequest $onboarding, string $stepName, float $progress): void
    {
        try {
            $clientId   = $onboarding->client_id;
            $clientName = $onboarding->client?->company_name ?? 'Client';

            $this->telegramGroupService->notifyClient($clientId, 'onboarding_step_completed', [
                'client_name' => $clientName,
                'step_name'   => $stepName,
                'progress'    => number_format($progress, 2),
            ]);
        } catch (\Throwable $e) {
            Log::error('OnboardingProgressService Telegram notification failed', [
                'onboarding_id' => $onboarding->id,
                'step_name'     => $stepName,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
