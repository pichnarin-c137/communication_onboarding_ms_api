<?php

use App\Http\Controllers\DevController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/dev/login/{email}', [DevController::class, 'loginAs']);

Route::get('/healthcheck', function () {
    $status = [
        'status'      => 'OK',
        'php_version' => phpversion(),
        'database'    => ['status' => 'unknown'],
        'redis'       => ['status' => 'unknown'],
    ];

    $httpCode = 200;

    try {
        DB::connection()->getPdo();
        $status['database'] = [
            'status'   => 'connected',
            'database' => DB::connection()->getDatabaseName(),
        ];
    } catch (Exception) {
        $status['database'] = ['status' => 'error', 'message' => 'Database connection failed'];
        $httpCode = 500;
    }

    try {
        Redis::ping();
        $status['redis'] = ['status' => 'connected'];
    } catch (Exception) {
        $status['redis'] = ['status' => 'error', 'message' => 'Redis connection failed'];
        $httpCode = 500;
    }

    $status['status']  = $httpCode === 500 ? 'ERROR' : 'OK';
    $status['message'] = $httpCode === 500
        ? 'Some services are down'
        : 'Customer Onboarding Management API is running smoothly.';

    return response()->json($status, $httpCode);
});
