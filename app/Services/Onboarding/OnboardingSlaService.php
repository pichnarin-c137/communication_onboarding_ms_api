<?php

namespace App\Services\Onboarding;

use App\Models\OnboardingRequest;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingSlaService
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function checkAllBreaches(): int
    {
        $breached = OnboardingRequest::whereNotNull('due_date')
            ->whereColumn('due_date', '<', \Illuminate\Support\Facades\DB::raw('CURRENT_DATE'))
            ->whereNull('sla_breached_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get();

        foreach ($breached as $onboarding) {
            try {
                $onboarding->update(['sla_breached_at' => now()]);

                $recipients = array_unique(array_filter([
                    $onboarding->appointment?->creator_id,
                ]));

                if (! empty($recipients)) {
                    $this->notificationService->notify(
                        $recipients,
                        'onboarding_sla_breached',
                        'Onboarding SLA Breached',
                        "Onboarding {$onboarding->request_code} has passed its due date and is now overdue.",
                        ['type' => 'onboarding_request', 'id' => $onboarding->id]
                    );
                }
            } catch (\Throwable $e) {
                Log::error('OnboardingSlaService: failed to process breach', [
                    'onboarding_id' => $onboarding->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $breached->count();
    }
}
