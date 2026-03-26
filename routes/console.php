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
