<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    Route::get('/get-profile', [UserController::class, 'getProfile']);

    Route::middleware(['role:admin'])->group(function () {
        Route::get('/user-detail/{userId}', [UserController::class, 'getUserById']);
        Route::get('/get-users', [UserController::class, 'listUsers']);
        Route::post('/create-user', [UserController::class, 'createUser']);
        Route::patch('/update-user-information/{userId}', [UserController::class, 'updateUserInformation']);
        Route::delete('/soft-delete-user/{userId}', [UserController::class, 'softDeleteUser']);
        Route::delete('/hard-delete-user/{userId}', [UserController::class, 'hardDeleteUser']);
        Route::patch('/restore-user/{userId}', [UserController::class, 'restoreUser']);
    });

    Route::middleware(['role:admin,sale,trainer'])->prefix('selection')->group(function () {
        Route::get('/trainers-dropdown', [UserController::class, 'listTrainers']);
        Route::get('/clients-dropdown', [UserController::class, 'listClients']);
    });

    Route::middleware(['role:sale,trainer'])->group(function () {
        Route::post('/media', [MediaController::class, 'upload'])->middleware('throttle:media_upload');
    });
});
