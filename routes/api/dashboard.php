<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api'])->prefix('dashboard')->group(function () {
    Route::get('/trainer', [DashboardController::class, 'trainerDashboard'])->name('dashboard.trainer');
    Route::get('/sale', [DashboardController::class, 'saleDashboard'])->name('dashboard.sale');
});
