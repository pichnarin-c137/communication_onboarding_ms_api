<?php

namespace App\Console\Commands;

use App\Jobs\SendWeeklyTrainerReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

// Runs at 17:00 UTC every Sunday (= Monday 00:00 for UTC+7 and beyond).
// Dispatches per-admin with a delay so each recipient receives the report
// at 08:00 on Monday in their own stored timezone.
class SendWeeklyTrainerReportCommand extends Command
{
    protected $signature = 'reports:weekly-trainer-report';

    protected $description = 'Dispatch weekly trainer performance report to all admins at 08:00 Monday their local time.';

    public function handle(): int
    {
        $admins = User::whereHas('role', fn ($q) => $q->where('role', 'admin'))
            ->with('settings:user_id,timezone')
            ->get(['id']);

        foreach ($admins as $admin) {
            $tz      = $admin->settings?->timezone ?? config('coms.user_settings.defaults.timezone', 'Asia/Phnom_Penh');
            $nowInTz = Carbon::now($tz);

            // Find the upcoming Monday 08:00 in the admin's timezone.
            // If it's already Monday before 08:00, use today; otherwise use next Monday.
            if ($nowInTz->dayOfWeek === Carbon::MONDAY && $nowInTz->hour < 8) {
                $deliverAt = $nowInTz->copy()->setTime(8, 0, 0);
            } else {
                $deliverAt = $nowInTz->copy()->next(Carbon::MONDAY)->setTime(8, 0, 0);
            }

            SendWeeklyTrainerReport::dispatch($admin->id)->delay($deliverAt);
        }

        $this->info("Scheduled weekly trainer report for {$admins->count()} admin(s) at their Monday 08:00 local time.");

        return self::SUCCESS;
    }
}
