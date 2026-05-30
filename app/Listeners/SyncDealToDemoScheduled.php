<?php

namespace App\Listeners;

use App\Events\DemoAppointmentBooked;
use App\Services\Crm\CrmDealService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridges the appointment domain to the CRM funnel: booking a demo moves its
 * deal into demo_scheduled. Failures are logged, never surfaced — booking the
 * appointment must succeed regardless of CRM state.
 */
class SyncDealToDemoScheduled
{
    public function __construct(
        private readonly CrmDealService $crmDealService,
    ) {}

    public function handle(DemoAppointmentBooked $event): void
    {
        try {
            $this->crmDealService->markDemoScheduled($event->crmDealId);
        } catch (Throwable $e) {
            Log::error('SyncDealToDemoScheduled failed', [
                'crm_deal_id' => $event->crmDealId,
                'appointment_id' => $event->appointmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
