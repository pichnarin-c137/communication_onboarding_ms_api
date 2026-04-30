<?php

namespace App\Console\Commands;

use App\Jobs\NotifyAppointmentNoShow;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;

//every 5 min Continuously — flags appointments 30+ min past start time
class CheckAppointmentNoShow extends Command
{
    protected $signature = 'appointment:check-no-show';

    protected $description = 'Detect appointments that have not started past their scheduled time and notify admins.';

    public function handle(): int
    {
        $thresholdMinutes = config('coms.reminders.no_show_threshold_minutes', 30);

        $appointments = Appointment::where('status', 'pending')
            ->whereDate('scheduled_date', now()->toDateString())
            ->whereNull('no_show_notified_at')
            ->get()
            ->filter(function (Appointment $appt) use ($thresholdMinutes) {
                $scheduledAt = Carbon::parse(
                    $appt->scheduled_date->format('Y-m-d').' '.$appt->scheduled_start_time,
                    config('coms.user_settings.defaults.timezone')
                );

                return now()->diffInMinutes($scheduledAt, false) <= -$thresholdMinutes;
            });

        foreach ($appointments as $appointment) {
            $appointment->update(['no_show_notified_at' => now()]);
            NotifyAppointmentNoShow::dispatch($appointment);
        }

        $count = $appointments->count();

        if ($count > 0) {
            $this->warn("{$count} appointment(s) flagged as no-show.");
        } else {
            $this->info('No no-show appointments detected.');
        }

        return self::SUCCESS;
    }
}
