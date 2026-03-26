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
    'onboarding_completion_threshold' => env('COMS_ONBOARDING_COMPLETION_THRESHOLD', 90.0),

    /*
    |--------------------------------------------------------------------------
    | Telegram Integration
    |--------------------------------------------------------------------------
    */
    'telegram_setup_token_ttl'      => env('COMS_TELEGRAM_SETUP_TOKEN_TTL', 3600),
    'telegram_message_retry_limit'  => env('COMS_TELEGRAM_MESSAGE_RETRY_LIMIT', 3),
    'telegram_webhook_secret'       => env('TELEGRAM_WEBHOOK_SECRET', ''),
    'telegram_default_language'     => env('COMS_TELEGRAM_DEFAULT_LANGUAGE', 'en'),
    'telegram_supported_languages'  => ['en', 'km'],

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
    ],

];
