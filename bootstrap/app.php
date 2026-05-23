<?php

use App\Exceptions\BaseException;
use App\Exceptions\JwtKeyNotFoundException;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\PreventMultiplePatch;
use App\Http\Middleware\ResolveAnalyticsScope;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyTelegramWebhookSecret;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Cloudflare proxy headers (tunnel locally, CDN in production)
        $middleware->prepend(TrustProxies::class);

        // Register custom middleware aliases
        $middleware->alias([
            'jwt.auth'          => JwtAuthenticate::class,
            'admin.only'        => AdminOnly::class,
            'role'              => RoleMiddleware::class,
            'telegram.webhook'  => VerifyTelegramWebhookSecret::class,
            'analytics.scope'   => ResolveAnalyticsScope::class,
        ]);

        // Reject array-body PATCH requests (must patch one resource at a time)
        $middleware->appendToGroup('api', PreventMultiplePatch::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                // Generate or extract request ID for tracking
                $requestId = $request->header('X-Request-ID', uniqid('req_', true));

                // Handle custom application exceptions with specific HTTP status codes
                if ($e instanceof BaseException) {
                    $body = $e->toArray();
                    $body['request_id'] = $requestId;
                    return response()->json($body, $e->getHttpStatusCode());
                }

                // Handle validation exceptions (422)
                if ($e instanceof ValidationException) {
                    $errors = collect($e->errors())->flatten()->values()->all();
                    $body = [
                        'success'    => false,
                        'message'    => count($errors) === 1 ? $errors[0] : 'Validation failed',
                        'error_code' => 'VALIDATION_ERROR',
                        'request_id' => $requestId,
                    ];
                    if (count($errors) > 1) {
                        $body['errors'] = $errors;
                    }
                    return response()->json($body, 422);
                }

                // Handle model not found (404)
                if ($e instanceof ModelNotFoundException) {
                    Log::warning('Resource not found', [
                        'exception'  => get_class($e),
                        'request_id' => $requestId,
                    ]);

                    return response()->json([
                        'success'    => false,
                        'message'    => 'Resource not found',
                        'error_code' => 'RESOURCE_NOT_FOUND',
                        'request_id' => $requestId,
                    ], 404);
                }

                // Handle database exceptions (500)
                if ($e instanceof QueryException) {
                    Log::error('Database error', [
                        'message'    => $e->getMessage(),
                        'code'       => $e->getCode(),
                        'request_id' => $requestId,
                    ]);

                    return response()->json([
                        'success'    => false,
                        'message'    => 'A database error occurred',
                        'error_code' => 'DATABASE_ERROR',
                        'request_id' => $requestId,
                    ], 500);
                }

                // Handle all other exceptions (500)
                Log::error('Unhandled exception', [
                    'exception'  => get_class($e),
                    'message'    => $e->getMessage(),
                    'file'       => $e->getFile(),
                    'line'       => $e->getLine(),
                    'request_id' => $requestId,
                ]);

                $response = [
                    'success'    => false,
                    'message'    => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
                    'error_code' => 'INTERNAL_SERVER_ERROR',
                    'request_id' => $requestId,
                ];

                // Include debug information only in debug mode
                if (config('app.debug')) {
                    $response['debug'] = [
                        'exception' => get_class($e),
                        'file'      => $e->getFile(),
                        'line'      => $e->getLine(),
                        'trace'     => collect($e->getTrace())->take(10)->toArray(),
                    ];
                }

                return response()->json($response, 500);
            }
        });

        // Report exceptions with additional context
        $exceptions->report(function (Throwable $e) {
            if ($e instanceof BaseException) {
                Log::channel('stack')->log(
                    $e instanceof JwtKeyNotFoundException ? 'critical' : 'error',
                    $e->getMessage(),
                    [
                        'exception' => get_class($e),
                        'context' => $e->getContext(),
                        'trace' => $e->getTraceAsString(),
                    ]
                );
            }
        });
    })->create();
