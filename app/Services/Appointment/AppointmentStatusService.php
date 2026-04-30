<?php

namespace App\Services\Appointment;

use App\Exceptions\Business\InvalidStatusTransitionException;
use App\Exceptions\Business\LeaveOfficeNotAllowedException;
use App\Models\Appointment;

class AppointmentStatusService
{
    private array $transitions = [
        'pending' => ['leave_office', 'in_progress', 'cancelled', 'rescheduled'],
        'leave_office' => ['in_progress', 'cancelled'],
        'in_progress' => ['done', 'cancelled'],
        'done' => [],
        'cancelled' => [],
        'rescheduled' => [],
    ];

    /**
     * @throws InvalidStatusTransitionException
     */
    public function validateTransition(Appointment $appt, string $to): void
    {
        if (! $this->canTransition($appt, $to)) {
            throw new InvalidStatusTransitionException(
                "Cannot transition appointment from '$appt->status' to '$to'."
            );
        }
    }

    public function canTransition(Appointment $appt, string $to): bool
    {
        $allowed = $this->transitions[$appt->status] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * @throws LeaveOfficeNotAllowedException
     */
    public function validateLeaveOffice(Appointment $appt): void
    {
        if ($appt->location_type === 'online') {
            throw new LeaveOfficeNotAllowedException;
        }
    }
}
