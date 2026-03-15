<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhookSecret
{
    /**
     * Verify that the incoming request carries the correct Telegram webhook secret token.
     *
     * Telegram sends the secret in the `X-Telegram-Bot-Api-Secret-Token` header.
     * If it does not match the configured value, return 403 with no body.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('coms.telegram_webhook_secret', '');
        $received = $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (! $expected || ! hash_equals($expected, $received)) {
            return response('', 403);
        }

        return $next($request);
    }
}
