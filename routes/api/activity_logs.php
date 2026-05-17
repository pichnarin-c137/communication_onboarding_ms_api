<?php

use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api', 'role:admin'])->group(function () {
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/users/{userId}/activity-logs', [ActivityLogController::class, 'forUser']);
});
