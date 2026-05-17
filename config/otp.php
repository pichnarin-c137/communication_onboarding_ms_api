<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    */
    'length' => env('OTP_LENGTH', 6),
    'expiry_min' => env('OTP_EXPIRY_MIN', 5), // minimum minutes
    'expiry_max' => env('OTP_EXPIRY_MAX', 10), // maximum minutes

    /*
    |--------------------------------------------------------------------------
    | OTP Bypass (development / testing only)
    |--------------------------------------------------------------------------
    | When BYPASS_OTP=true: every login uses the fixed code 111111 and no
    | email is sent. Set to false (or omit) in production to require real OTP
    | delivery via Gmail.
    */
    'bypass_otp' => env('BYPASS_OTP', false),
];
