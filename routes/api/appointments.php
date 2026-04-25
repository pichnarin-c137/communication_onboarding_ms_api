<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentStudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api'])->prefix('appointments')->group(function () {
    Route::get('/', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/{id}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::patch('/{id}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::patch('/{id}/leave-office', [AppointmentController::class, 'leaveOffice'])->name('appointments.leaveOffice');
    Route::patch('/{id}/start', [AppointmentController::class, 'start'])->name('appointments.start');
    Route::patch('/{id}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
    Route::post('/{id}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::post('/{id}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');

    Route::get('/{id}/students', [AppointmentStudentController::class, 'index'])->name('appointments.students.index');
    Route::post('/{id}/students', [AppointmentStudentController::class, 'store'])->name('appointments.students.store');
    Route::patch('/{id}/students/{sid}/attendance', [AppointmentStudentController::class, 'markAttendance'])->name('appointments.students.attendance');
});
