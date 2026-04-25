<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleOAuthController;
use Illuminate\Support\Facades\Route;

// Public auth endpoints (no JWT required)
Route::prefix('auth')->middleware(['throttle:auth'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

    // Forgot password — user submits email, receives reset link
    Route::patch('/forgot-password', [AuthController::class, 'forgotPassword']);

    // Reset password — user submits token from email + new password
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken'])
    ->middleware(['throttle:auth_refresh']);

Route::get('/google/callback', [GoogleOAuthController::class, 'callback']);

// Authenticated endpoints (JWT required)
Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Change password — logged-in user provides old + new password
    Route::patch('/auth/change-password', [AuthController::class, 'changePassword']);

    Route::get('/google/redirect', [GoogleOAuthController::class, 'redirect']);
    Route::get('/google/status', [GoogleOAuthController::class, 'status']);
    Route::delete('/google/disconnect', [GoogleOAuthController::class, 'disconnect']);
});
