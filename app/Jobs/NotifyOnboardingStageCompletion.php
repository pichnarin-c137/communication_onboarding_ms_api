<?php

namespace App\Jobs;

use App\Models\OnboardingRequest;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyOnboardingStageCompletion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly string $onboardingId,
        public readonly string $stepName,
        public readonly float $progress,
    ) {
        $this->onQueue('default');
    }

    public function handle(NotificationService $notificationService): void
    {
        $onboarding = OnboardingRequest::with('appointment')->find($this->onboardingId);

        if (! $onboarding) {
            return;
        }

        $saleId = $onboarding->appointment?->creator_id;

        if (! $saleId) {
            return;
        }

        $clientName = $onboarding->client?->company_name ?? 'Client';
        $progress   = number_format($this->progress, 1);

        $notificationService->notify(
            [$saleId],
            'onboarding_stage_completed',
            'Onboarding Stage Completed',
            "Stage '{$this->stepName}' completed for {$clientName}. Overall progress: {$progress}%.",
            ['type' => 'onboarding_request', 'id' => $this->onboardingId],
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyOnboardingStageCompletion: failed after all retries.', [
            'onboarding_id' => $this->onboardingId,
            'step_name'     => $this->stepName,
            'error'         => $e->getMessage(),
        ]);
    }
}
