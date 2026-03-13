<?php

namespace App\Services;

use App\Exceptions\AccountSuspendedException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\InvalidTokenTypeException;
use App\Exceptions\JwtKeyNotFoundException;
use App\Exceptions\RefreshTokenRevokedException;
use App\Exceptions\TokenExpiredException;
use App\Exceptions\UserNotFoundException;
use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $privateKey;

    private string $publicKey;

    private int $accessTokenExpiry;

    private int $refreshTokenExpiry;

    public function __construct()
    {
        try {
            $privateKeyPath = storage_path(config('jwt.private_key_path', 'keys/jwt_private.pem'));
            $publicKeyPath = storage_path(config('jwt.public_key_path', 'keys/jwt_public.pem'));

            // Check if key files exist
            if (! file_exists($privateKeyPath)) {
                throw new JwtKeyNotFoundException("JWT private key not found at: {$privateKeyPath}");
            }

            if (! file_exists($publicKeyPath)) {
                throw new JwtKeyNotFoundException("JWT public key not found at: {$publicKeyPath}");
            }

            // Read key files
            $this->privateKey = file_get_contents($privateKeyPath);
            $this->publicKey = file_get_contents($publicKeyPath);

            // Verify successful read
            if ($this->privateKey === false || $this->publicKey === false) {
                throw new JwtKeyNotFoundException('Failed to read JWT key files');
            }
        } catch (JwtKeyNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new JwtKeyNotFoundException('Error loading JWT keys: '.$e->getMessage(), 0, $e);
        }

        $this->accessTokenExpiry = config('jwt.access_token_expiry', 1440); // minutes
        $this->refreshTokenExpiry = config('jwt.refresh_token_expiry', 43200); // minutes
    }

    /**
     * Generate access token (short-lived)
     */
    public function generateAccessToken(User $user): string
    {
        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'user_id' => $user->id,
            'role' => $user->role->role,
            'iat' => time(),
            'exp' => time() + ($this->accessTokenExpiry * 60),
            'type' => 'access',
        ];

        return JWT::encode($payload, $this->privateKey, 'RS256');
    }

    /**
     * Generate refresh token (long-lived) and store in DB
     */
    public function generateRefreshToken(User $user): string
    {
        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'user_id' => $user->id,
            'iat' => time(),
            'exp' => time() + ($this->refreshTokenExpiry * 60),
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(32)), // Unique token ID
        ];

        $token = JWT::encode($payload, $this->privateKey, 'RS256');

        // Store in database with hash for uniqueness
        RefreshToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'expires_at' => Carbon::now()->addMinutes($this->refreshTokenExpiry),
        ]);

        return $token;
    }

    /**
     * Validate and decode token
     */
    public function validateToken(string $token): object
    {
        try {
            return JWT::decode($token, new Key($this->publicKey, 'RS256'));
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new TokenExpiredException('Token has expired', 0, $e);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            throw new InvalidTokenException('Token signature is invalid', 0, $e);
        } catch (\Throwable $e) {
            throw new InvalidTokenException('Invalid token: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        // Validate refresh token
        $decoded = $this->validateToken($refreshToken);

        if ($decoded->type !== 'refresh') {
            throw new InvalidTokenTypeException('Token must be a refresh token');
        }

        // Check if token exists in DB and is valid (query by hash for performance)
        $tokenHash = hash('sha256', $refreshToken);
        $storedToken = RefreshToken::where('token_hash', $tokenHash)
            ->where('user_id', $decoded->user_id)
            ->first();

        if (! $storedToken) {
            throw new InvalidTokenException('Refresh token not found in database');
        }

        if ($storedToken->is_revoked) {
            throw new RefreshTokenRevokedException('Refresh token has been revoked');
        }

        if (! $storedToken->isValid()) {
            throw new TokenExpiredException('Refresh token has expired');
        }

        // Get user
        $user = User::with('role')->find($decoded->user_id);

        if (! $user) {
            throw new UserNotFoundException('User not found', 0, null, ['user_id' => $decoded->user_id]);
        }

        if ($user->isSuspended()) {
            throw new AccountSuspendedException('User account is suspended');
        }

        // Generate new access token
        $newAccessToken = $this->generateAccessToken($user);

        return [
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenExpiry * 60,
        ];
    }

    /**
     * Revoke refresh token
     */
    public function revokeRefreshToken(string $token): void
    {
        $tokenHash = hash('sha256', $token);
        $refreshToken = RefreshToken::where('token_hash', $tokenHash)->first();

        if ($refreshToken) {
            $refreshToken->revoke();
        }
    }

    /**
     * Revoke all user's refresh tokens
     */
    public function revokeAllUserTokens(string $userId): void
    {
        RefreshToken::where('user_id', $userId)
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);
    }
}
