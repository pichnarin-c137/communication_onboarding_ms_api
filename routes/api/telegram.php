<?php

use App\Http\Controllers\Telegram\TelegramGroupController;
use App\Http\Controllers\Telegram\TelegramMessageController;
use App\Http\Controllers\Telegram\TelegramSetupController;
use Illuminate\Support\Facades\Route;

// Authenticated Telegram management (sale + admin only)
Route::middleware(['jwt.auth', 'throttle:api', 'role:sale,admin'])->prefix('telegram')->group(function () {
    Route::post('/setup-token', [TelegramSetupController::class, 'generateToken'])->name('telegram.setup-token');

    Route::get('/groups', [TelegramGroupController::class, 'index'])->name('telegram.groups.index');
    Route::get('/groups/{id}', [TelegramGroupController::class, 'show'])->name('telegram.groups.show');
    Route::patch('/groups/{id}/disconnect', [TelegramGroupController::class, 'disconnect'])->name('telegram.groups.disconnect');
    Route::patch('/groups/{id}/reconnect', [TelegramGroupController::class, 'reconnect'])->name('telegram.groups.reconnect');
    Route::patch('/groups/{id}/language', [TelegramGroupController::class, 'updateLanguage'])->name('telegram.groups.language');
    Route::post('/groups/{id}/test-message', [TelegramGroupController::class, 'testMessage'])->name('telegram.groups.test-message');

    Route::get('/messages', [TelegramMessageController::class, 'index'])->name('telegram.messages.index');
});

// Telegram webhook — no auth, protected by secret header middleware only
Route::post('/telegram/webhook', [TelegramSetupController::class, 'webhook'])
    ->middleware('telegram.webhook')
    ->name('telegram.webhook');
