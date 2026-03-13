<?php

namespace Tests\Helpers;

use App\Models\User;
use App\Services\JwtService;

trait GeneratesTokens
{
    /**
     * Generate an access token for a user
     */
    protected function generateAccessToken(User $user): string
    {
        return app(JwtService::class)->generateAccessToken($user);
    }

    /**
     * Generate a refresh token for a user
     */
    protected function generateRefreshToken(User $user): string
    {
        return app(JwtService::class)->generateRefreshToken($user);
    }

    /**
     * Generate both access and refresh tokens
     */
    protected function generateTokens(User $user): array
    {
        $jwtService = app(JwtService::class);

        return [
            'access_token' => $jwtService->generateAccessToken($user),
            'refresh_token' => $jwtService->generateRefreshToken($user),
        ];
    }

    /**
     * Get authorization headers with Bearer token
     */
    protected function authHeaders(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get authorization headers for a user
     */
    protected function authHeadersFor(User $user): array
    {
        $token = $this->generateAccessToken($user);

        return $this->authHeaders($token);
    }
}
