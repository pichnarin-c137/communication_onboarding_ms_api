<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A demo appointment linked to a CRM deal was completed. The CRM domain reacts
 * by stamping the deal and notifying its owner — the deal stays in the funnel.
 */
class DemoAppointmentCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $crmDealId,
        public readonly string $appointmentId,
    ) {}
}
