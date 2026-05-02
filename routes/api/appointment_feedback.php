<?php

use App\Http\Controllers\AppointmentFeedbackController;
use Illuminate\Support\Facades\Route;

Route::get('/appointments/feedback/{token}', [AppointmentFeedbackController::class, 'showForm'])
    ->name('appointments.feedback.form');
Route::post('/appointments/feedback/{token}', [AppointmentFeedbackController::class, 'submitViaForm'])
    ->name('appointments.feedback.submit');

Route::middleware(['jwt.auth', 'throttle:api', 'role:sale,admin,trainer'])->group(function () {
    Route::get('/appointments/{id}/feedback', [AppointmentFeedbackController::class, 'index'])
        ->name('appointments.feedback.index');
});
