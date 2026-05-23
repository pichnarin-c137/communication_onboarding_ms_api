<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__.'/api/system.php';
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/users.php';
    require __DIR__.'/api/activity_logs.php';
    require __DIR__.'/api/notifications.php';
    require __DIR__.'/api/appointment_feedback.php';
    require __DIR__.'/api/appointments.php';
    require __DIR__.'/api/onboarding.php';
    require __DIR__.'/api/dashboard.php';
    require __DIR__.'/api/tracking.php';
    require __DIR__.'/api/broadcasting.php';
    require __DIR__.'/api/playlists.php';
    require __DIR__.'/api/business.php';
    require __DIR__.'/api/telegram.php';
    require __DIR__.'/api/analytics.php';
});
