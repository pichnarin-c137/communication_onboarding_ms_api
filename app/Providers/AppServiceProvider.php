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
use App\Services\Onboarding\OnboardingFeedbackService;
use App\Services\Onboarding\OnboardingProgressService;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\OnboardingSlaService;
use App\Services\Onboarding\OnboardingTriggerService;
use App\Services\Telegram\TelegramGroupService;
use App\Services\Telegram\TelegramMessageTemplate;
use App\Services\Telegram\TelegramWebhookService;
use App\Services\Business\BusinessTypeService;
use App\Services\Business\CompanyService;
use App\Services\Business\DocumentExtractionService;
use App\Services\Playlist\PlaylistService;
use App\Services\Playlist\PlaylistTelegramService;
use App\Services\Playlist\PlaylistVideoService;
use App\Services\Tracking\AnomalyDetectionService;
use App\Services\Tracking\EtaService;
use App\Services\Tracking\TrainerStatusService;
use App\Services\Tracking\TrainerTrackingService;
use App\Services\UserSettingsService;
use Carbon\Carbon;
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
        $this->app->singleton(UserSettingsService::class);
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
        $this->app->singleton(OnboardingFeedbackService::class);
        $this->app->singleton(OnboardingSlaService::class);

        // Telegram integration layer
        $this->app->singleton(TelegramGroupService::class, fn ($app) => new TelegramGroupService(
            $app->make(TelegramMessageTemplate::class),
            $app->make(UserSettingsService::class),
        ));

        $this->app->singleton(TelegramWebhookService::class, fn ($app) => new TelegramWebhookService(
            $app->make(TelegramGroupService::class),
        ));

        // Playlist layer
        $this->app->singleton(PlaylistService::class);
        $this->app->singleton(PlaylistVideoService::class);
        $this->app->singleton(PlaylistTelegramService::class);

        // Tracking layer
        $this->app->singleton(AnomalyDetectionService::class);
        $this->app->singleton(TrainerTrackingService::class);
        $this->app->singleton(TrainerStatusService::class);
        $this->app->singleton(EtaService::class);

        // Business Management layer
        $this->app->singleton(BusinessTypeService::class);
        $this->app->singleton(CompanyService::class);
        $this->app->singleton(DocumentExtractionService::class);

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
        Carbon::serializeUsing(function (Carbon $carbon) {
            return $carbon->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP');
        });

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

        // Location ping — scoped by authenticated user
        RateLimiter::for('location_ping', function (Request $request) {
            return Limit::perMinute(config('coms.rate_limits.location_ping', 10))
                ->by($request->get('auth_user_id', $request->ip()))
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Too many location pings. Please slow down.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429)->withHeaders(['Retry-After' => 30]));
        });

        // Document extraction — scoped by authenticated user
        RateLimiter::for('document_extract', function (Request $request) {
            return Limit::perMinute(config('coms.document_extract_rate_limit', 10))
                ->by($request->get('auth_user_id', $request->ip()))
                ->response(fn () => response()->json([
                    'success'    => false,
                    'message'    => 'Too many document extraction requests. Please wait.',
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
