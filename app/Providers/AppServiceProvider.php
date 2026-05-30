<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\AppointmentFeedback;
use App\Models\OnboardingClientFeedback;
use App\Models\OnboardingRequest;
use App\Observers\AnalyticsCacheObserver;
use App\Observers\FeedbackSentimentObserver;
use App\Observers\OnboardingRequestStatusObserver;
use App\Services\Analytics\AnalyticsAnomalyService;
use App\Services\Analytics\AnalyticsAppointmentService;
use App\Services\Analytics\AnalyticsCohortService;
use App\Services\Analytics\AnalyticsEngagementService;
use App\Services\Analytics\AnalyticsForecastService;
use App\Services\Analytics\AnalyticsHeatmapService;
use App\Services\Analytics\AnalyticsOnboardingBreakdownService;
use App\Services\Analytics\AnalyticsOnboardingFunnelService;
use App\Services\Analytics\AnalyticsOverviewService;
use App\Services\Analytics\AnalyticsSalesLeaderboardService;
use App\Services\Analytics\AnalyticsSatisfactionService;
use App\Services\Analytics\AnalyticsSentimentService;
use App\Services\Analytics\AnalyticsTrainerLeaderboardService;
use App\Services\Analytics\AnalyticsTrainerScorecardService;
use App\Services\Analytics\AnalyticsTrendsService;
use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsScopeResolver;
use App\Services\Analytics\Support\SentimentClassifier;
use App\Services\Analytics\Support\TrainerAttribution;
use App\Services\Appointment\AppointmentAnalyticsService;
use App\Services\Appointment\AppointmentConflictService;
use App\Services\Appointment\AppointmentService;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Appointment\AppointmentFeedbackService;
use App\Services\Appointment\DemoCompletionService;
use App\Services\Business\BusinessTypeService;
use App\Services\Business\CompanyService;
use App\Services\Business\DocumentExtractionService;
use App\Services\Crm\CrmContactService;
use App\Services\Crm\CrmDealService;
use App\Services\Logging\ActivityLogger;
use App\Services\Notification\NotificationService;
use App\Services\Notification\TelegramService;
use App\Services\Onboarding\LessonSendService;
use App\Services\Onboarding\OnboardingFeedbackService;
use App\Services\Onboarding\OnboardingProgressService;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\OnboardingSlaService;
use App\Services\Onboarding\OnboardingTriggerService;
use App\Services\Playlist\PlaylistService;
use App\Services\Playlist\PlaylistTelegramService;
use App\Services\Playlist\PlaylistVideoService;
use App\Services\Sale\SaleTrainerAssignmentService;
use App\Services\Telegram\TelegramGroupService;
use App\Services\Telegram\TelegramMessageTemplate;
use App\Services\Telegram\TelegramWebhookService;
use App\Services\Tracking\AnomalyDetectionService;
use App\Services\Tracking\EtaService;
use App\Services\Tracking\TrainerStatusService;
use App\Services\Tracking\TrainerTrackingService;
use App\Services\R2Service;
use App\Services\UserSettingsService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use App\Events\DemoAppointmentBooked;
use App\Events\DemoAppointmentCompleted;
use App\Listeners\RecordDealDemoCompletion;
use App\Listeners\SyncDealToDemoScheduled;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Pusher\Pusher;

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
        $this->app->singleton(R2Service::class);

        // Appointment layer
        $this->app->singleton(AppointmentAnalyticsService::class);
        $this->app->singleton(AppointmentConflictService::class);
        $this->app->singleton(AppointmentStatusService::class);
        $this->app->singleton(DemoCompletionService::class);
        $this->app->singleton(AppointmentFeedbackService::class);
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

        // Sale dedicated trainer roster
        $this->app->singleton(SaleTrainerAssignmentService::class);

        // CRM layer
        $this->app->singleton(CrmContactService::class);
        $this->app->singleton(CrmDealService::class);

        // Analytics dashboard
        $this->app->singleton(AnalyticsCache::class);
        $this->app->singleton(AnalyticsScopeResolver::class);
        $this->app->singleton(TrainerAttribution::class);
        $this->app->singleton(AnalyticsOverviewService::class);
        $this->app->singleton(AnalyticsTrendsService::class);
        $this->app->singleton(AnalyticsAppointmentService::class);
        $this->app->singleton(AnalyticsOnboardingFunnelService::class);
        $this->app->singleton(AnalyticsSatisfactionService::class);
        $this->app->singleton(AnalyticsTrainerLeaderboardService::class);
        $this->app->singleton(AnalyticsTrainerScorecardService::class);
        $this->app->singleton(AnalyticsSalesLeaderboardService::class);
        $this->app->singleton(AnalyticsHeatmapService::class);
        $this->app->singleton(AnalyticsEngagementService::class);
        $this->app->singleton(AnalyticsOnboardingBreakdownService::class);

        // Analytics — Phase 4 intelligence
        $this->app->singleton(SentimentClassifier::class);
        $this->app->singleton(AnalyticsSentimentService::class);
        $this->app->singleton(AnalyticsAnomalyService::class);
        $this->app->singleton(AnalyticsCohortService::class);
        $this->app->singleton(AnalyticsForecastService::class);

        // Broadcasting
        $this->app->singleton(Pusher::class, function () {
            return new Pusher(
                config('reverb.apps.apps.0.key'),
                config('reverb.apps.apps.0.secret'),
                config('reverb.apps.apps.0.app_id'),
                [
                    'host' => 'reverb',
                    'port' => 8080,
                    'scheme' => 'http',
                    'encrypted' => false,
                    'useTLS' => false,
                ]
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::serializeUsing(function (CarbonInterface $carbon) {
            $tz = app()->bound('request.timezone')
                ? app('request.timezone')
                : config('coms.user_settings.defaults.timezone', 'Asia/Phnom_Penh');

            return $carbon->setTimezone($tz)->format('Y-m-d\TH:i:s.uP');
        });

        $this->configureRateLimiting();

        // Analytics observers — status history + cache invalidation
        OnboardingRequest::observe(OnboardingRequestStatusObserver::class);
        Appointment::observe(AnalyticsCacheObserver::class);
        OnboardingRequest::observe(AnalyticsCacheObserver::class);
        AppointmentFeedback::observe(AnalyticsCacheObserver::class);
        OnboardingClientFeedback::observe(AnalyticsCacheObserver::class);

        // Populate comment sentiment on new feedback writes (Eloquent create).
        OnboardingClientFeedback::observe(FeedbackSentimentObserver::class);
        AppointmentFeedback::observe(FeedbackSentimentObserver::class);

        // CRM ↔ appointment bridge — keeps the two domains decoupled (no
        // service-to-service calls; the deal stage follows the demo via events).
        Event::listen(DemoAppointmentBooked::class, SyncDealToDemoScheduled::class);
        Event::listen(DemoAppointmentCompleted::class, RecordDealDemoCompletion::class);
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
                    'success' => false,
                    'message' => 'Too many document extraction requests. Please wait.',
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

        // Analytics namespace — scoped by user
        RateLimiter::for('analytics', function (Request $request) {
            return Limit::perMinute(config('coms.analytics.rate_limit_per_min', 60))
                ->by($request->get('auth_user_id', $request->ip()))
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Analytics rate limit exceeded. Please slow down.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429)->withHeaders(['Retry-After' => 60]));
        });
    }
}
