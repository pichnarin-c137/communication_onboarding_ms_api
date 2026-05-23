<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaPresignController;
use App\Http\Controllers\MyDedicatedTrainersController;
use App\Http\Controllers\SaleTrainerAssignmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    Route::get('/get-profile', [UserController::class, 'getProfile']);

    Route::middleware(['role:admin'])->group(function () {
        Route::get('/user-detail/{userId}', [UserController::class, 'getUserById']);
        Route::get('/get-users', [UserController::class, 'listUsers']);
        Route::post('/create-user', [UserController::class, 'createUser']);
        Route::patch('/update-user-information/{userId}', [UserController::class, 'updateUserInformation']);
        Route::patch('/users/{userId}/credentials', [UserController::class, 'updateCredentials']);
        Route::patch('/users/{userId}/suspend', [UserController::class, 'toggleSuspension']);
        Route::post('/users/{userId}/force-password-reset', [UserController::class, 'forcePasswordReset']);
        Route::delete('/soft-delete-user/{userId}', [UserController::class, 'softDeleteUser']);
        Route::delete('/hard-delete-user/{userId}', [UserController::class, 'hardDeleteUser']);
        Route::patch('/restore-user/{userId}', [UserController::class, 'restoreUser']);

        Route::get('/users/{saleUserId}/dedicated-trainers', [SaleTrainerAssignmentController::class, 'show']);
        Route::put('/users/{saleUserId}/dedicated-trainers', [SaleTrainerAssignmentController::class, 'replace']);
    });

    Route::middleware(['role:admin,sale,trainer'])->prefix('selection')->group(function () {
        Route::get('/trainers-dropdown', [UserController::class, 'listTrainers']);
        Route::get('/clients-dropdown', [UserController::class, 'listClients']);
    });

    Route::middleware(['role:sale'])->prefix('me')->group(function () {
        Route::get('/dedicated-trainers', [MyDedicatedTrainersController::class, 'index']);
        Route::get('/dedicated-trainers/{trainerId}/overview', [MyDedicatedTrainersController::class, 'overview']);
    });

    Route::middleware(['role:sale,trainer'])->group(function () {
        Route::post('/media', [MediaController::class, 'upload'])->middleware('throttle:media_upload');
        Route::post('/media/presign', [MediaPresignController::class, 'presign'])->middleware('throttle:media_upload');
        Route::post('/media/confirm', [MediaPresignController::class, 'confirm'])->middleware('throttle:media_upload');
    });
});
