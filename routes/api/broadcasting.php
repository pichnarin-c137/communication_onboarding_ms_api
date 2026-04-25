<?php

use App\Http\Controllers\BroadcastController;
use Illuminate\Support\Facades\Route;

// Pusher private channel auth — JWT-aware, no Auth::user() dependency
Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    Route::post('/broadcasting/auth', [BroadcastController::class, 'auth']);
});
