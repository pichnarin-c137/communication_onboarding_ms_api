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
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Rules
    |--------------------------------------------------------------------------
    */
    'onboarding_completion_threshold' => env('COMS_ONBOARDING_COMPLETION_THRESHOLD', 90.0),

];
