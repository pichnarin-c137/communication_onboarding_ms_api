<?php

namespace App\Http\Middleware;

use App\Exceptions\Business\MultiplePatchException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PreventMultiplePatch
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('PATCH')) {
            $body = $request->json()->all();

            // json()->all() returns a numerically-indexed array when the root is a JSON array
            if (array_is_list($body) && count($body) > 0) {
                throw new MultiplePatchException();
            }

            // Prevent duplicate concurrent PATCH requests (same user + URL + body)
            $userId = $request->get('auth_user_id', $request->ip());
            $fingerprint = hash('sha256', $userId . '|' . $request->fullUrl() . '|' . json_encode($body));
            $cacheKey = "patch_lock:{$fingerprint}";

            if (Cache::has($cacheKey)) {
                throw new MultiplePatchException('Duplicate request detected. Please wait before retrying.');
            }

            Cache::put($cacheKey, true, 5); // 5-second lock
        }

        return $next($request);
    }
}
