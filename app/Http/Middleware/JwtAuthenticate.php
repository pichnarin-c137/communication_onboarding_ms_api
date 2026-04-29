<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidTokenException;
use App\Exceptions\InvalidTokenTypeException;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            throw new InvalidTokenException('No authentication token provided');
        }

        // Validate token (will throw specific exceptions if invalid)
        $decoded = $this->jwtService->validateToken($token);

        // Ensure it's an access token
        if ($decoded->type !== 'access') {
            throw new InvalidTokenTypeException('Token must be an access token');
        }

        // Read timezone from token payload — set at login, no DB query needed
        $timezone = $decoded->timezone ?? 'Asia/Phnom_Penh';

        // Bind per-request so serializeUsing and services can read it
        app()->instance('request.timezone', $timezone);

        $request->merge([
            'auth_user_id'  => $decoded->user_id,
            'auth_role'     => $decoded->role,
            'auth_timezone' => $timezone,
        ]);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
