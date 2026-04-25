<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/{id}/read', [NotificationController::class, 'markRead']);
        Route::patch('/read-all', [NotificationController::class, 'markAllRead']);
    });

    Route::prefix('settings')->group(function () {
        Route::get('/', [UserSettingsController::class, 'show']);
        Route::patch('/', [UserSettingsController::class, 'update']);
    });
});
