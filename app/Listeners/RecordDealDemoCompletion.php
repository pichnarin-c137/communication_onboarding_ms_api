<?php

namespace App\Listeners;

use App\Events\DemoAppointmentCompleted;
use App\Services\Crm\CrmDealService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridges the appointment domain to the CRM funnel: completing a demo stamps the
 * deal and nudges its owner to advance it. The deal stays in the funnel.
 */
class RecordDealDemoCompletion
{
    public function __construct(
        private readonly CrmDealService $crmDealService,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(DemoAppointmentCompleted $event): void
    {
        try {
            $deal = $this->crmDealService->recordDemoCompleted($event->crmDealId);

            if (! $deal || ! $deal->assigned_to) {
                return;
            }

            $this->notificationService->notify(
                [$deal->assigned_to],
                'crm_demo_completed',
                'Demo Completed',
                "The demo for deal '{$deal->title}' is complete. Advance it to won or lost when you're ready.",
                ['type' => 'crm_deal', 'id' => $deal->id],
            );
        } catch (Throwable $e) {
            Log::error('RecordDealDemoCompletion failed', [
                'crm_deal_id' => $event->crmDealId,
                'appointment_id' => $event->appointmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
