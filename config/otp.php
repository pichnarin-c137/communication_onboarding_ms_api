<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    */
    'length' => env('OTP_LENGTH', 4),
    'expiry_min' => env('OTP_EXPIRY_MIN', 5), // minimum minutes
    'expiry_max' => env('OTP_EXPIRY_MAX', 10), // maximum minutes
];
