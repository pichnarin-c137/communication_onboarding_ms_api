<?php

namespace App\Services\Appointment;

use App\Exceptions\Business\TrainerScheduleConflictException;
use App\Models\Appointment;

class AppointmentConflictService
{
    public function checkConflict(string $trainerId, string $date, string $start, string $end, ?string $excludeId = null): void
    {
        $query = Appointment::where('trainer_id', $trainerId)
            ->where('scheduled_date', $date)
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->where(function ($q) use ($start, $end) {
                // Overlap condition: existing start < new end AND existing end > new start
                $q->where('scheduled_start_time', '<', $end)
                    ->where('scheduled_end_time', '>', $start);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new TrainerScheduleConflictException(
                'Trainer already has an appointment scheduled during this time slot.'
            );
        }
    }
}
