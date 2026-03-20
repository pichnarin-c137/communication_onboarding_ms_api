<?php

namespace App\Providers;

use App\Services\Appointment\AppointmentConflictService;
use App\Services\Appointment\AppointmentService;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Appointment\DemoCompletionService;
use App\Services\Logging\ActivityLogger;
use App\Services\Notification\NotificationService;
use App\Services\Notification\TelegramService;
use App\Services\Onboarding\LessonSendService;
use App\Services\Onboarding\OnboardingProgressService;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\OnboardingTriggerService;
use App\Services\Telegram\TelegramGroupService;
use App\Services\Telegram\TelegramMessageTemplate;
use App\Services\Telegram\TelegramWebhookService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ActivityLogger::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(TelegramService::class);

        // Appointment layer
        $this->app->singleton(AppointmentConflictService::class);
        $this->app->singleton(AppointmentStatusService::class);
        $this->app->singleton(DemoCompletionService::class);
        $this->app->singleton(OnboardingTriggerService::class);
        $this->app->singleton(AppointmentService::class);

        // Onboarding layer
        $this->app->singleton(OnboardingProgressService::class);
        $this->app->singleton(LessonSendService::class);
        $this->app->singleton(OnboardingService::class);

        // Telegram integration layer
        $this->app->singleton(TelegramGroupService::class, fn ($app) => new TelegramGroupService(
            $app->make(TelegramService::class),
            $app->make(TelegramMessageTemplate::class),
        ));

        $this->app->singleton(TelegramWebhookService::class, fn ($app) => new TelegramWebhookService(
            $app->make(TelegramGroupService::class),
        ));

        // Broadcasting
        $this->app->singleton(\Pusher\Pusher::class, function () {
            $cfg = config('services.pusher');
            return new \Pusher\Pusher(
                $cfg['app_key'],
                $cfg['secret'],
                $cfg['app_id'],
                $cfg['options']
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Auth endpoints — scoped by IP address
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(config('coms.rate_limits.auth', 10))
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Too many login attempts. Please wait before trying again.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429)->withHeaders(['Retry-After' => 60]));
        });

        // Token refresh — scoped by IP address (stricter)
        RateLimiter::for('auth_refresh', function (Request $request) {
            return Limit::perMinute(config('coms.rate_limits.auth_refresh', 5))
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Too many token refresh attempts. Please wait.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429)->withHeaders(['Retry-After' => 60]));
        });

        // File upload — scoped by authenticated user
        RateLimiter::for('media_upload', function (Request $request) {
            return Limit::perMinute(config('coms.rate_limits.media_upload', 20))
                ->by($request->get('auth_user_id', $request->ip()))
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'File upload limit reached. Please wait before uploading again.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429)->withHeaders(['Retry-After' => 60]));
        });

        // Lesson send (triggers Telegram) — scoped by user
        RateLimiter::for('lesson_send', function (Request $request) {
            return Limit::perMinute(config('coms.rate_limits.lesson_send', 30))
                ->by($request->get('auth_user_id', $request->ip()))
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Too many lesson send requests. Please slow down.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429)->withHeaders(['Retry-After' => 60]));
        });

        // Onboarding progress refresh — scoped by user
        RateLimiter::for('onboarding_refresh', function (Request $request) {
            return Limit::perMinute(config('coms.rate_limits.onboarding_refresh', 10))
                ->by($request->get('auth_user_id', $request->ip()))
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Progress refresh limit reached. Please wait a moment.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429)->withHeaders(['Retry-After' => 60]));
        });

        // General authenticated API — scoped by user
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(config('coms.rate_limits.api', 120))
                ->by($request->get('auth_user_id', $request->ip()))
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429)->withHeaders(['Retry-After' => 60]));
        });
    }
}
