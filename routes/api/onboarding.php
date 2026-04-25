<?php

use App\Http\Controllers\OnboardingCompanyInfoController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OnboardingFeedbackController;
use App\Http\Controllers\OnboardingLessonController;
use App\Http\Controllers\OnboardingPolicyController;
use App\Http\Controllers\OnboardingSystemAnalysisController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:api'])->prefix('onboarding')->group(function () {
    // Core CRUD
    Route::get('/', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::get('/{id}', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::get('/{id}/sales', [OnboardingController::class, 'sales'])->name('onboarding.sales');

    Route::post('/{id}/refresh-progress', [OnboardingController::class, 'refreshProgress'])
        ->middleware('throttle:onboarding_refresh')
        ->name('onboarding.refreshProgress');

    // Status transitions
    Route::patch('/{id}/start', [OnboardingController::class, 'start'])->name('onboarding.start');
    Route::patch('/{id}/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('/{id}/cancel', [OnboardingController::class, 'cancel'])->name('onboarding.cancel');
    Route::patch('/{id}/hold', [OnboardingController::class, 'hold'])->middleware('role:trainer')->name('onboarding.hold');
    Route::patch('/{id}/resume', [OnboardingController::class, 'resumeHold'])->middleware('role:trainer')->name('onboarding.resume');
    Route::patch('/{id}/request-revision', [OnboardingController::class, 'requestRevision'])->middleware('role:sale,admin')->name('onboarding.requestRevision');
    Route::patch('/{id}/acknowledge-revision', [OnboardingController::class, 'acknowledgeRevision'])->middleware('role:trainer')->name('onboarding.acknowledgeRevision');
    Route::patch('/{id}/reopen', [OnboardingController::class, 'reopen'])->middleware('role:sale,admin')->name('onboarding.reopen');

    // Management
    Route::patch('/{id}/reassign-trainer', [OnboardingController::class, 'reassignTrainer'])->middleware('role:admin')->name('onboarding.reassignTrainer');
    Route::patch('/{id}/due-date', [OnboardingController::class, 'setDueDate'])->middleware('role:sale,admin')->name('onboarding.setDueDate');
    Route::get('/{id}/appointments', [OnboardingController::class, 'linkedAppointments'])->name('onboarding.linkedAppointments');
    Route::get('/{id}/cycles', [OnboardingController::class, 'cycles'])->name('onboarding.cycles');
    Route::get('/{id}/trainer-history', [OnboardingController::class, 'trainerHistory'])->middleware('role:admin')->name('onboarding.trainerHistory');

    // Company info
    Route::get('/{id}/company-info', [OnboardingCompanyInfoController::class, 'show'])->name('onboarding.companyInfo.show');
    Route::patch('/{id}/company-info', [OnboardingCompanyInfoController::class, 'update'])->name('onboarding.companyInfo.update');

    // System analysis
    Route::get('/{id}/system-analysis', [OnboardingSystemAnalysisController::class, 'show'])->name('onboarding.systemAnalysis.show');
    Route::patch('/{id}/system-analysis', [OnboardingSystemAnalysisController::class, 'update'])->name('onboarding.systemAnalysis.update');

    // Policies
    Route::get('/{id}/policies', [OnboardingPolicyController::class, 'index'])->name('onboarding.policies.index');
    Route::post('/{id}/policies', [OnboardingPolicyController::class, 'store'])->name('onboarding.policies.store');
    Route::patch('/{id}/policies/{pid}/check', [OnboardingPolicyController::class, 'check'])->name('onboarding.policies.check');
    Route::patch('/{id}/policies/{pid}/uncheck', [OnboardingPolicyController::class, 'uncheck'])->name('onboarding.policies.uncheck');
    Route::delete('/{id}/policies/{pid}', [OnboardingPolicyController::class, 'destroy'])->name('onboarding.policies.destroy');

    // Lessons
    Route::get('/{id}/lessons', [OnboardingLessonController::class, 'index'])->name('onboarding.lessons.index');
    Route::post('/{id}/lessons', [OnboardingLessonController::class, 'store'])->name('onboarding.lessons.store');
    Route::patch('/{id}/lessons/{lid}', [OnboardingLessonController::class, 'update'])->name('onboarding.lessons.update');
    Route::delete('/{id}/lessons/{lid}', [OnboardingLessonController::class, 'destroy'])->name('onboarding.lessons.destroy');
    Route::post('/{id}/lessons/{lid}/send', [OnboardingLessonController::class, 'send'])
        ->middleware('throttle:lesson_send')
        ->name('onboarding.lessons.send');

    // Client feedback (authenticated)
    Route::post('/{id}/feedback/request', [OnboardingFeedbackController::class, 'request'])->middleware('role:trainer,admin')->name('onboarding.feedback.request');
    Route::post('/{id}/feedback', [OnboardingFeedbackController::class, 'submitManual'])->middleware('role:trainer,admin')->name('onboarding.feedback.submitManual');
    Route::get('/{id}/feedback', [OnboardingFeedbackController::class, 'show'])->name('onboarding.feedback.show');
});

// Public feedback routes — no auth, protected only by token validation
Route::get('/feedback/{token}', [OnboardingFeedbackController::class, 'showForm'])->name('feedback.form');
Route::post('/feedback/{token}', [OnboardingFeedbackController::class, 'submitViaEmail'])->name('feedback.submit');
