<?php

use App\Http\Controllers\Analytics\AnalyticsAppointmentController;
use App\Http\Controllers\Analytics\AnalyticsEngagementController;
use App\Http\Controllers\Analytics\AnalyticsHeatmapController;
use App\Http\Controllers\Analytics\AnalyticsOnboardingController;
use App\Http\Controllers\Analytics\AnalyticsOverviewController;
use App\Http\Controllers\Analytics\AnalyticsSalesController;
use App\Http\Controllers\Analytics\AnalyticsSatisfactionController;
use App\Http\Controllers\Analytics\AnalyticsTrainerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth', 'throttle:analytics', 'analytics.scope'])
    ->prefix('analytics')
    ->group(function () {
        Route::get('/overview',     [AnalyticsOverviewController::class, 'overview'])->name('analytics.overview');
        Route::get('/trends',       [AnalyticsOverviewController::class, 'trends'])->name('analytics.trends');
        Route::get('/appointments', [AnalyticsAppointmentController::class, 'index'])->name('analytics.appointments');
        Route::get('/satisfaction', [AnalyticsSatisfactionController::class, 'index'])->name('analytics.satisfaction');

        Route::middleware('role:admin,sale')->group(function () {
            Route::get('/onboarding-funnel',     [AnalyticsOnboardingController::class, 'funnel'])->name('analytics.onboarding.funnel');
            Route::get('/onboardings/breakdown', [AnalyticsOnboardingController::class, 'breakdown'])->name('analytics.onboarding.breakdown');
            Route::get('/engagement',            [AnalyticsEngagementController::class, 'index'])->name('analytics.engagement');
            Route::get('/trainers',              [AnalyticsTrainerController::class, 'leaderboard'])->name('analytics.trainers.leaderboard');
        });

        Route::get('/trainers/{id}', [AnalyticsTrainerController::class, 'scorecard'])
            ->whereUuid('id')
            ->name('analytics.trainers.scorecard');

        Route::middleware('role:admin')->group(function () {
            Route::get('/sales',   [AnalyticsSalesController::class, 'leaderboard'])->name('analytics.sales.leaderboard');
            Route::get('/heatmap', [AnalyticsHeatmapController::class, 'index'])->name('analytics.heatmap');
        });
    });
