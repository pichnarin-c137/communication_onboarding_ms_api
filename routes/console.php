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

// Reports
Schedule::command('reports:daily-digest')->dailyAt('08:00');
Schedule::command('reports:weekly-trainer-report')->weeklyOn(1, '08:00');

// Horizon metrics snapshots — required for the throughput graph in the dashboard
Schedule::command('horizon:snapshot')->everyFiveMinutes();
