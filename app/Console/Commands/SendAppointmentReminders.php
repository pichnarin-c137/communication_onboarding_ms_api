<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointment:send-reminders';

    protected $description = 'Dispatch reminder notifications for upcoming appointments (24h and 1h windows).';

    public function handle(): int
    {
        $window24h = config('coms.reminders.appointment_24h_window_minutes', 15);
        $window1h = config('coms.reminders.appointment_1h_window_minutes', 10);

        $dispatched = 0;
        $dispatched += $this->dispatchReminders('24h', 24 * 60, $window24h);
        $dispatched += $this->dispatchReminders('1h', 60, $window1h);

        $this->info("Dispatched {$dispatched} appointment reminder(s).");

        return self::SUCCESS;
    }

    private function dispatchReminders(string $type, int $minutesFromNow, int $windowMinutes): int
    {
        $sentAtColumn = $type === '24h' ? 'reminder_24h_sent_at' : 'reminder_1h_sent_at';

        $windowStart = now()->addMinutes($minutesFromNow - $windowMinutes);
        $windowEnd = now()->addMinutes($minutesFromNow + $windowMinutes);

        $appointments = Appointment::where('status', 'pending')
            ->whereNotNull('trainer_id')
            ->whereNull($sentAtColumn)
            ->get()
            ->filter(function (Appointment $appt) use ($windowStart, $windowEnd) {
                $scheduledAt = Carbon::parse(
                    $appt->scheduled_date->format('Y-m-d').' '.$appt->scheduled_start_time
                );

                return $scheduledAt->between($windowStart, $windowEnd);
            });

        foreach ($appointments as $appointment) {
            $appointment->update([$sentAtColumn => now()]);
            SendAppointmentReminder::dispatch($appointment, $type);
        }

        return $appointments->count();
    }
}
