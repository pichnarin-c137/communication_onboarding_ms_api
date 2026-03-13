<?php

namespace Tests\Unit\Services;

use App\Exceptions\AccountSuspendedException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\InvalidTokenTypeException;
use App\Exceptions\RefreshTokenRevokedException;
use App\Exceptions\TokenExpiredException;
use App\Exceptions\UserNotFoundException;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\JwtService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tests\TestCase;

class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
    }

    /** @test */
    public function it_generates_valid_access_token()
    {
        $user = $this->createUser();

        $token = $this->jwtService->generateAccessToken($user);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    /** @test */
    public function access_token_contains_correct_payload_structure()
    {
        $user = $this->createUser();

        $token = $this->jwtService->generateAccessToken($user);

        // Decode token to verify payload
        $publicKey = file_get_contents(storage_path('keys/jwt_public.pem'));
        $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

        $this->assertEquals($user->id, $decoded->user_id);
        $this->assertEquals($user->role->role, $decoded->role);
        $this->assertEquals('access', $decoded->type);
        $this->assertObjectHasProperty('iss', $decoded);
        $this->assertObjectHasProperty('iat', $decoded);
        $this->assertObjectHasProperty('exp', $decoded);
    }

    /** @test */
    public function it_generates_refresh_token_and_stores_in_database()
    {
        $user = $this->createUser();

        $token = $this->jwtService->generateRefreshToken($user);

        $this->assertIsString($token);
        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'token' => $token,
            'is_revoked' => false,
        ]);
    }

    /** @test */
    public function refresh_token_has_longer_expiry_than_access_token()
    {
        $user = $this->createUser();

        $accessToken = $this->jwtService->generateAccessToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        $publicKey = file_get_contents(storage_path('keys/jwt_public.pem'));
        $accessDecoded = JWT::decode($accessToken, new Key($publicKey, 'RS256'));
        $refreshDecoded = JWT::decode($refreshToken, new Key($publicKey, 'RS256'));

        $this->assertGreaterThan($accessDecoded->exp, $refreshDecoded->exp);
    }

    /** @test */
    public function it_validates_and_decodes_valid_token()
    {
        $user = $this->createUser();
        $token = $this->jwtService->generateAccessToken($user);

        $decoded = $this->jwtService->validateToken($token);

        $this->assertEquals($user->id, $decoded->user_id);
        $this->assertEquals('access', $decoded->type);
    }

    /** @test */
    public function it_throws_exception_for_expired_token()
    {
        $this->expectException(TokenExpiredException::class);

        $user = $this->createUser();

        // Manually create an expired token
        $privateKey = file_get_contents(storage_path('keys/jwt_private.pem'));
        $expiredPayload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'user_id' => $user->id,
            'role' => $user->role->role,
            'iat' => time() - 86400, // Issued 1 day ago
            'exp' => time() - 3600,   // Expired 1 hour ago
            'type' => 'access',
        ];

        $expiredToken = JWT::encode($expiredPayload, $privateKey, 'RS256');

        $this->jwtService->validateToken($expiredToken);
    }

    /** @test */
    public function it_throws_exception_for_invalid_token()
    {
        $this->expectException(InvalidTokenException::class);

        $this->jwtService->validateToken('invalid.token.here');
    }

    /** @test */
    public function it_refreshes_access_token_with_valid_refresh_token()
    {
        $user = $this->createUser();
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        $result = $this->jwtService->refreshAccessToken($refreshToken);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertEquals('Bearer', $result['token_type']);
    }

    /** @test */
    public function it_throws_exception_when_using_access_token_as_refresh_token()
    {
        $this->expectException(InvalidTokenTypeException::class);
        $this->expectExceptionMessage('Token must be a refresh token');

        $user = $this->createUser();
        $accessToken = $this->jwtService->generateAccessToken($user);

        $this->jwtService->refreshAccessToken($accessToken);
    }

    /** @test */
    public function it_throws_exception_for_revoked_refresh_token()
    {
        $this->expectException(RefreshTokenRevokedException::class);

        $user = $this->createUser();
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        // Revoke the token
        $this->jwtService->revokeRefreshToken($refreshToken);

        $this->jwtService->refreshAccessToken($refreshToken);
    }

    /** @test */
    public function it_throws_exception_for_suspended_user_during_token_refresh()
    {
        $this->expectException(AccountSuspendedException::class);

        $user = $this->createSuspendedUser();
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        // Suspend user after token generation
        $user->update(['is_suspended' => true]);

        $this->jwtService->refreshAccessToken($refreshToken);
    }

    /** @test */
    public function it_revokes_refresh_token()
    {
        $user = $this->createUser();
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        $this->jwtService->revokeRefreshToken($refreshToken);

        $this->assertDatabaseHas('refresh_tokens', [
            'token' => $refreshToken,
            'is_revoked' => true,
        ]);
    }

    /** @test */
    public function it_revokes_all_user_tokens()
    {
        $user = $this->createUser();

        // Generate multiple refresh tokens
        $token1 = $this->jwtService->generateRefreshToken($user);
        $token2 = $this->jwtService->generateRefreshToken($user);
        $token3 = $this->jwtService->generateRefreshToken($user);

        $this->jwtService->revokeAllUserTokens($user->id);

        // All tokens should be revoked
        $this->assertEquals(0, RefreshToken::where('user_id', $user->id)
            ->where('is_revoked', false)
            ->count());
    }

    /** @test */
    public function it_handles_non_existent_user_during_token_refresh()
    {
        $this->expectException(UserNotFoundException::class);

        $user = $this->createUser();
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        // Delete the user
        $user->delete();

        $this->jwtService->refreshAccessToken($refreshToken);
    }
}
