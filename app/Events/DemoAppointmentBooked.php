<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A demo appointment was booked against a CRM deal. The CRM domain reacts by
 * advancing the deal into the demo_scheduled stage.
 */
class DemoAppointmentBooked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $crmDealId,
        public readonly string $appointmentId,
    ) {}
}
