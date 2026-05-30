<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache TTLs (seconds)
    |--------------------------------------------------------------------------
    | Redis is the source of truth for caching. All keys follow the convention:
    |   {resource}:{identifier}:{variant}
    */
    'cache' => [
        'appointment_list_ttl' => env('COMS_APPOINTMENT_LIST_TTL', 300),    // 5 min
        'appointment_show_ttl' => env('COMS_APPOINTMENT_SHOW_TTL', 600),    // 10 min
        'onboarding_list_ttl' => env('COMS_ONBOARDING_LIST_TTL', 300),     // 5 min
        'onboarding_show_ttl' => env('COMS_ONBOARDING_SHOW_TTL', 600),     // 10 min
        'onboarding_progress_ttl' => env('COMS_ONBOARDING_PROGRESS_TTL', 300), // 5 min
        'dashboard_ttl' => env('COMS_DASHBOARD_TTL', 180),           // 3 min
        'crm_pipeline_ttl' => env('COMS_CRM_PIPELINE_TTL', 300),     // 5 min
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (max requests per minute)
    |--------------------------------------------------------------------------
    | Auth endpoints are scoped by IP. All others are scoped by authenticated user ID.
    */
    'rate_limits' => [
        'auth' => env('COMS_RATE_AUTH', 10),
        'auth_refresh' => env('COMS_RATE_AUTH_REFRESH', 5),
        'media_upload' => env('COMS_RATE_MEDIA_UPLOAD', 20),
        'lesson_send' => env('COMS_RATE_LESSON_SEND', 30),
        'onboarding_refresh' => env('COMS_RATE_ONBOARDING_REFRESH', 10),
        'api' => env('COMS_RATE_API', 120),
        'location_ping' => env('COMS_RATE_LOCATION_PING', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Rules
    |--------------------------------------------------------------------------
    */
    'onboarding_completion_threshold' => env('COMS_ONBOARDING_COMPLETION_THRESHOLD', 99.0),
    'onboarding_due_days' => env('COMS_ONBOARDING_DUE_DAYS', 30),
    'feedback_token_ttl_days' => env('COMS_FEEDBACK_TOKEN_TTL_DAYS', 7),
    'password_reset_ttl_minutes' => env('COMS_PASSWORD_RESET_TTL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | CRM Module
    |--------------------------------------------------------------------------
    | Enum reference for contacts/deals. Stage flow:
    |   prospect -> demo_scheduled -> proposal_sent -> negotiating -> won (terminal)
    |                                                              -> lost (terminal)
    */
    'crm' => [
        'contact_statuses' => ['prospect', 'deal_active', 'won', 'lost'],
        'deal_sources' => ['referral', 'cold_call', 'website', 'event', 'other'],
        'deal_stages' => ['prospect', 'demo_scheduled', 'proposal_sent', 'negotiating', 'won', 'lost'],
        // Stages a deal may be set to via PATCH (terminal stages go through /won and /lost only).
        'deal_editable_stages' => ['prospect', 'demo_scheduled', 'proposal_sent', 'negotiating'],
        'deal_terminal_stages' => ['won', 'lost'],
        'client_code_prefix' => env('COMS_CRM_CLIENT_CODE_PREFIX', 'CL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Integration
    |--------------------------------------------------------------------------
    */
    'telegram_setup_token_ttl' => env('COMS_TELEGRAM_SETUP_TOKEN_TTL', 3600),
    'telegram_message_retry_limit' => env('COMS_TELEGRAM_MESSAGE_RETRY_LIMIT', 3),
    'telegram_webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
    'telegram_default_language' => env('COMS_TELEGRAM_DEFAULT_LANGUAGE', 'en'),
    'telegram_supported_languages' => ['en', 'km'],

    /*
    |--------------------------------------------------------------------------
    | Playlist Management
    |--------------------------------------------------------------------------
    */
    'playlist_list_ttl' => env('COMS_PLAYLIST_LIST_TTL', 300),
    'telegram_send_queue' => env('COMS_TELEGRAM_SEND_QUEUE', 'high'),

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    */
    'broadcast_queue' => env('COMS_BROADCAST_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Trainer Tracking
    |--------------------------------------------------------------------------
    */
    'tracking' => [
        'ping_interval_seconds' => env('COMS_TRACKING_PING_INTERVAL', 30),
        'idle_ping_interval_seconds' => env('COMS_TRACKING_IDLE_PING_INTERVAL', 300),
        'max_accuracy_meters' => env('COMS_TRACKING_MAX_ACCURACY', 100),
        'geofence_accuracy_meters' => env('COMS_TRACKING_GEOFENCE_ACCURACY', 50),
        'geofence_radius_meters' => env('COMS_TRACKING_GEOFENCE_RADIUS', 200),
        'max_speed_kmh' => env('COMS_TRACKING_MAX_SPEED', 200),
        'max_ping_age_seconds' => env('COMS_TRACKING_MAX_PING_AGE', 60),
        'eta_recalc_interval_seconds' => env('COMS_TRACKING_ETA_RECALC', 60),
        'trail_flush_interval_minutes' => env('COMS_TRACKING_TRAIL_FLUSH', 5),
        'ping_retention_days' => env('COMS_TRACKING_PING_RETENTION', 30),
        'eta_cache_ttl' => env('COMS_TRACKING_ETA_CACHE_TTL', 120),
        'status_cache_ttl' => env('COMS_TRACKING_STATUS_TTL', 86400),
        'anomaly_travel_delay_factor' => env('COMS_ANOMALY_TRAVEL_DELAY', 2.0),
        'anomaly_session_overtime_factor' => env('COMS_ANOMALY_SESSION_OVERTIME', 1.5),
        'anomaly_departure_warning_minutes' => env('COMS_ANOMALY_DEPARTURE_WARNING', 30),
        'osrm_base_url' => env('COMS_OSRM_BASE_URL', 'https://router.project-osrm.org'),
        'route_estimate_ttl' => env('COMS_TRACKING_ROUTE_ESTIMATE_TTL', 86400), // 24h — branch/client locations are static
        'haversine_fallback_speed_kmh' => env('COMS_HAVERSINE_FALLBACK_SPEED_KMH', 40),
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Management
    |--------------------------------------------------------------------------
    */
    'business' => [
        'business_type_list_ttl' => env('COMS_BUSINESS_TYPE_LIST_TTL', 600),   // 10 min
        'business_type_show_ttl' => env('COMS_BUSINESS_TYPE_SHOW_TTL', 1800),  // 30 min
        'company_list_ttl' => env('COMS_COMPANY_LIST_TTL', 300),         // 5 min
        'company_show_ttl' => env('COMS_COMPANY_SHOW_TTL', 600),         // 10 min
    ],

    'document_extract_rate_limit' => env('COMS_DOCUMENT_EXTRACT_RATE_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | User Settings
    |--------------------------------------------------------------------------
    */
    'user_settings' => [
        'cache_ttl' => env('COMS_USER_SETTINGS_CACHE_TTL', 600),
        'defaults' => [
            'in_app_notifications' => true,
            'telegram_notifications' => true,
            'language' => 'en',
            'timezone' => env('COMS_DEFAULT_TIMEZONE', 'Asia/Phnom_Penh'),
            'items_per_page' => 15,
            'theme' => 'light',
            'quiet_hours_enabled' => false,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
        ],
        'supported_languages' => ['en', 'km'],
        'supported_themes' => ['light', 'dark'],
        'items_per_page_min' => 5,
        'items_per_page_max' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Automated Reminders & Alerts
    |--------------------------------------------------------------------------
    */
    'reminders' => [
        'appointment_24h_window_minutes' => env('COMS_REMINDER_24H_WINDOW', 15),
        'appointment_1h_window_minutes'  => env('COMS_REMINDER_1H_WINDOW', 10),
        'no_show_threshold_minutes'      => env('COMS_NO_SHOW_THRESHOLD', 30),
        'sla_warning_days_before'        => env('COMS_SLA_WARNING_DAYS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Appointment Health Thresholds
    |--------------------------------------------------------------------------
    */
    'appointment_health' => [
        'pending_too_long_days'      => env('COMS_PENDING_TOO_LONG_DAYS', 3),
        'starting_late_minutes'      => env('COMS_STARTING_LATE_MINUTES', 15),
        'late_to_client_minutes'     => env('COMS_LATE_TO_CLIENT_MINUTES', 30),
        'overtime_threshold_minutes' => env('COMS_OVERTIME_MINUTES', 15),
        'started_late_minutes'       => env('COMS_STARTED_LATE_MINUTES', 20),
    ],

    'reports_queue' => env('COMS_REPORTS_QUEUE', 'reports'),

    /*
    |--------------------------------------------------------------------------
    | Sale Dedicated Trainer Roster
    |--------------------------------------------------------------------------
    | Workload caps and minimum roster size for the sale-trainer assignment
    | feature. Used by SaleTrainerAssignmentService when admins create or
    | replace a sale user's dedicated trainer roster.
    */
    'sale_roster' => [
        'min_trainers' => env('COMS_SALE_ROSTER_MIN_TRAINERS', 1),
        'max_concurrent_active_onboardings_per_trainer' => env('COMS_SALE_ROSTER_MAX_ONBOARDINGS', 5),
        'max_sales_per_trainer' => env('COMS_SALE_ROSTER_MAX_SALES', 3),
        'block_if_in_progress_appointment' => env('COMS_SALE_ROSTER_BLOCK_IN_PROGRESS', true),
        'max_pending_appointments_per_trainer' => env('COMS_SALE_ROSTER_MAX_PENDING', 10),
        'enriched_list_ttl' => env('COMS_SALE_ROSTER_ENRICHED_LIST_TTL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Dashboard
    |--------------------------------------------------------------------------
    | Tunables for the /analytics/* namespace.
    | - cache_ttl:        response cache lifetime in seconds (5 min default)
    | - rate_limit_per_min: throttle:analytics ceiling (60/min/user)
    | - max_range_days:   reject any window where (to-from) exceeds this
    | - low_rating_threshold: ratings <= this trigger /satisfaction alerts
    | - alert_window_days: how far back to scan for low-rating alerts
    */
    'analytics' => [
        // Cache store to use for analytics responses. null = use cache.default (works in tests).
        // In production set COMS_ANALYTICS_CACHE_STORE=redis for tag-based invalidation.
        'cache_store'          => env('COMS_ANALYTICS_CACHE_STORE'),
        'cache_ttl'            => env('COMS_ANALYTICS_CACHE_TTL', 300),
        'rate_limit_per_min'   => env('COMS_ANALYTICS_RATE_LIMIT', 60),
        'max_range_days'       => env('COMS_ANALYTICS_MAX_RANGE_DAYS', 365),
        'business_timezone'    => env('COMS_BUSINESS_TIMEZONE', 'Asia/Phnom_Penh'),
        'low_rating_threshold' => env('COMS_ANALYTICS_LOW_RATING', 2),
        'alert_window_days'    => env('COMS_ANALYTICS_ALERT_WINDOW_DAYS', 7),
        'low_alert_limit'      => env('COMS_ANALYTICS_LOW_ALERT_LIMIT', 20),
        'sparkline_buckets'    => env('COMS_ANALYTICS_SPARKLINE_BUCKETS', 5),
        'on_time_threshold_min' => env('COMS_ANALYTICS_ON_TIME_THRESHOLD_MIN', 15),
        'demo_to_training_window_days' => env('COMS_ANALYTICS_DEMO_TO_TRAINING_DAYS', 30),

        /*
        | Phase 4 — Intelligence endpoints (sentiment, anomalies, cohorts, forecast)
        | - anomaly_sigma_threshold:      z-score above which a bucket is flagged
        | - anomaly_min_baseline_buckets: minimum history buckets before flagging
        | - forecast_horizon_default:     periods to project when none requested
        | - forecast_method:              'holt' | 'linear' | 'moving_avg'
        | - sentiment_provider:           'lexicon' | 'llm'
        | - sentiment_cache_ttl:          sentiment is expensive; cache far longer
        */
        'anomaly_sigma_threshold'      => env('COMS_ANALYTICS_ANOMALY_SIGMA', 2.0),
        'anomaly_min_baseline_buckets' => env('COMS_ANALYTICS_ANOMALY_MIN_BASELINE', 6),
        'forecast_horizon_default'     => env('COMS_ANALYTICS_FORECAST_HORIZON', 4),
        'forecast_method'              => env('COMS_ANALYTICS_FORECAST_METHOD', 'holt'),
        'sentiment_provider'           => env('COMS_ANALYTICS_SENTIMENT_PROVIDER', 'lexicon'),
        'sentiment_cache_ttl'          => env('COMS_ANALYTICS_SENTIMENT_CACHE_TTL', 86400),
    ],

];
