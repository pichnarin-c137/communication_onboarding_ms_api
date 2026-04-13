<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Key Paths
    |--------------------------------------------------------------------------
    */
    'private_key_path' => env('JWT_PRIVATE_KEY_PATH', 'keys/jwt_private.pem'),
    'public_key_path' => env('JWT_PUBLIC_KEY_PATH', 'keys/jwt_public.pem'),

    /*
    |--------------------------------------------------------------------------
    | Token Expiration Times (in minutes)
    |--------------------------------------------------------------------------
    */
    'access_token_expiry' => env('JWT_ACCESS_TOKEN_EXPIRY', 60),
    'refresh_token_expiry' => env('JWT_REFRESH_TOKEN_EXPIRY', 1440),
    'rememberme_access_token_expiry' => env('JWT_REMEMBERME_TOKEN_EXPIRY', 1440),
    'rememberme_refresh_token_expiry' => env('JWT_REMEMBERME_REFRESH_TOKEN_EXPIRY', 43200),

    /*
    |--------------------------------------------------------------------------
    | Algorithm
    |--------------------------------------------------------------------------
    */
    'algorithm' => 'RS256',
];
