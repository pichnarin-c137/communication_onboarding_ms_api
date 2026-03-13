<?php

namespace App\Services;

use App\Models\OAuthToken;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

class GoogleOAuthService
{
    public function getRedirectUrl(string $userId): string
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        $response = $driver
            ->stateless()
            ->scopes(['https://www.googleapis.com/auth/calendar'])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
                'state' => $userId,
            ])
            ->redirect();

        return $response->getTargetUrl();
    }

    public function handleCallback(string $code, string $userId): OAuthToken
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        /** @var SocialiteUser $googleUser */
        $googleUser = $driver->stateless()->user();

        $oauthToken = OAuthToken::updateOrCreate(
            [
                'user_id' => $userId,
                'provider' => 'google',
            ],
            [
                'provider_user_id' => $googleUser->getId(),
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken,
                'scopes' => $googleUser->approvedScopes ?? [],
                'expires_at' => $googleUser->expiresIn
                    ? now()->addSeconds($googleUser->expiresIn)
                    : null,
            ]
        );

        Log::info('Google OAuth tokens stored', [
            'user_id' => $userId,
            'provider_user_id' => $googleUser->getId(),
        ]);

        return $oauthToken;
    }

    public function getStatus(string $userId): array
    {
        $token = OAuthToken::where('user_id', $userId)
            ->where('provider', 'google')
            ->first();

        if (! $token) {
            return [
                'connected' => false,
                'provider' => 'google',
                'scopes' => [],
                'email' => null,
            ];
        }

        return [
            'connected' => true,
            'provider' => 'google',
            'scopes' => $token->scopes ?? [],
            'provider_user_id' => $token->provider_user_id,
        ];
    }

    public function disconnect(string $userId): void
    {
        OAuthToken::where('user_id', $userId)
            ->where('provider', 'google')
            ->delete();

        Log::info('Google OAuth disconnected', ['user_id' => $userId]);
    }

    public function getValidAccessToken(string $userId): ?string
    {
        $token = OAuthToken::where('user_id', $userId)
            ->where('provider', 'google')
            ->first();

        if (! $token) {
            return null;
        }

        if (! $token->isExpired()) {
            return $token->access_token;
        }

        if (! $token->refresh_token) {
            Log::warning('Google token expired and no refresh token available', [
                'user_id' => $userId,
            ]);

            return null;
        }

        return $this->refreshAccessToken($token);
    }

    private function refreshAccessToken(OAuthToken $token): ?string
    {
        /** @var Response $response */
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $token->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            Log::error('Failed to refresh Google access token', [
                'user_id' => $token->user_id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        Log::info('Google access token refreshed', ['user_id' => $token->user_id]);

        return $data['access_token'];
    }
}
