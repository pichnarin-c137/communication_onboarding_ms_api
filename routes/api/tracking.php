<?php

use App\Http\Controllers\TrainerLiveStatusController;
use App\Http\Controllers\TrainerTrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    // Trainer actions (trainer only)
    Route::middleware(['role:trainer'])->group(function () {
        Route::post('/trainer/location-ping', [TrainerTrackingController::class, 'ping'])
            ->middleware('throttle:location_ping')
            ->name('trainer.location-ping');
        Route::post('/trainer/status', [TrainerTrackingController::class, 'changeStatus'])->name('trainer.status');
        Route::post('/trainer/checkin', [TrainerTrackingController::class, 'checkin'])->name('trainer.checkin');
    });

    // Live tracking views (sale + admin)
    Route::middleware(['role:sale,admin'])->group(function () {
        Route::get('/trainers/live-status', [TrainerLiveStatusController::class, 'liveStatus'])->name('trainers.live-status');
        Route::get('/trainers/{id}/today-activity', [TrainerLiveStatusController::class, 'todayActivity'])->name('trainers.today-activity');
        Route::get('/trainers/{id}/activity-log', [TrainerLiveStatusController::class, 'activityLog'])->name('trainers.activity-log');
        Route::get('/customers/locations', [TrainerLiveStatusController::class, 'customerLocations'])->name('customers.locations');
    });
});
