<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyDigest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

// Runs at 00:00 UTC daily. Dispatches per-user with a delay so each recipient
// receives their digest at 08:00 in their own stored timezone.
class SendDailyDigestCommand extends Command
{
    protected $signature = 'reports:daily-digest';

    protected $description = 'Dispatch daily digest notifications to all admins and sales at 08:00 their local time.';

    public function handle(): int
    {
        $users = User::whereHas('role', fn ($q) => $q->whereIn('role', ['admin', 'sale']))
            ->with('settings:user_id,timezone')
            ->get(['id']);

        foreach ($users as $user) {
            $tz        = $user->settings?->timezone ?? config('coms.user_settings.defaults.timezone', 'Asia/Phnom_Penh');
            $deliverAt = Carbon::now($tz)->setTime(8, 0, 0);

            if ($deliverAt->isPast()) {
                $deliverAt->addDay();
            }

            SendDailyDigest::dispatch($user->id)->delay($deliverAt);
        }

        $this->info("Scheduled daily digest for {$users->count()} user(s) at their 08:00 local time.");

        return self::SUCCESS;
    }
}
