<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Trainer tracking scheduled tasks
Schedule::command('tracking:recalculate-eta')->everyMinute();
Schedule::command('tracking:flush-and-check')->everyFiveMinutes();
Schedule::command('tracking:cleanup-pings')->dailyAt('03:00');

// Onboarding SLA checks
Schedule::command('onboarding:check-sla')->daily();
Schedule::command('onboarding:check-sla-warning')->dailyAt('07:00');

// Appointment automated checks
Schedule::command('appointment:send-reminders')->everyFiveMinutes();
Schedule::command('appointment:check-no-show')->everyFiveMinutes();

// Reports — run early UTC so per-user delays land at 08:00 in each user's stored timezone
// 00:00 UTC = 07:00 Asia/Phnom_Penh → 1h delay puts Phnom Penh users at 08:00 local
// 17:00 UTC Sunday = 00:00 Monday Asia/Phnom_Penh → 8h delay puts Phnom Penh admins at 08:00 Monday local
Schedule::command('reports:daily-digest')->dailyAt('00:00');
Schedule::command('reports:weekly-trainer-report')->weeklyOn(0, '17:00');

// Horizon metrics snapshots — required for the throughput graph in the dashboard
Schedule::command('horizon:snapshot')->everyFiveMinutes();
