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
use Throwable;

class SendOnboardingSlaWarning implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly OnboardingRequest $onboarding,
    ) {
        $this->onQueue('high');
    }

    public function handle(NotificationService $notificationService): void
    {
        $recipients = array_unique(array_filter([
            $this->onboarding->trainer_id,
            $this->onboarding->appointment?->creator_id,
        ]));

        if (empty($recipients)) {
            return;
        }

        $progress = number_format($this->onboarding->progress_percentage, 1);
        $dueDate = $this->onboarding->due_date->format('Y-m-d');

        $notificationService->notify(
            $recipients,
            'onboarding_sla_warning',
            'Onboarding Due Soon',
            "Onboarding {$this->onboarding->request_code} is due on $dueDate. Current progress: $progress%.",
            ['type' => 'onboarding_request', 'id' => $this->onboarding->id],
        );
    }

    public function failed(Throwable $e): void
    {
        Log::error('SendOnboardingSlaWarning: failed after all retries.', [
            'onboarding_id' => $this->onboarding->id,
            'error' => $e->getMessage(),
        ]);
    }
}
