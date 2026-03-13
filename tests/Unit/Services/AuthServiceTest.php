<?php

namespace Tests\Unit\Services;

use App\Exceptions\AccountSuspendedException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\OtpExpiredException;
use App\Models\Credential;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\OtpService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private AuthService $authService;

    private JwtService $jwtService;

    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(AuthService::class);
        $this->jwtService = app(JwtService::class);
        $this->otpService = app(OtpService::class);
    }

    /** @test */
    public function it_initiates_login_with_email()
    {
        Mail::fake();

        $user = $this->createUser([], ['email' => 'test@example.com']);

        $credential = $this->authService->initiateLogin('test@example.com', 'Test@12345');

        $this->assertInstanceOf(Credential::class, $credential);
        $this->assertEquals($user->id, $credential->user_id);
        $this->assertNotNull($credential->otp);
    }

    /** @test */
    public function it_initiates_login_with_username()
    {
        Mail::fake();

        $user = $this->createUser([], ['username' => 'testuser']);

        $credential = $this->authService->initiateLogin('testuser', 'Test@12345');

        $this->assertInstanceOf(Credential::class, $credential);
        $this->assertEquals($user->id, $credential->user_id);
        $this->assertNotNull($credential->otp);
    }

    /** @test */
    public function it_throws_exception_for_non_existent_user()
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid username or password');

        $this->authService->initiateLogin('nonexistent@example.com', 'password');
    }

    /** @test */
    public function it_throws_exception_for_wrong_password()
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid username or password');

        $user = $this->createUser([], ['email' => 'test@example.com']);

        $this->authService->initiateLogin('test@example.com', 'WrongPassword123!');
    }

    /** @test */
    public function it_throws_exception_for_suspended_account()
    {
        $this->expectException(AccountSuspendedException::class);
        $this->expectExceptionMessage('Your account has been suspended');

        $user = $this->createSuspendedUser();

        $this->authService->initiateLogin($user->credential->email, 'Test@12345');
    }

    /** @test */
    public function it_sends_otp_during_login()
    {
        Mail::fake();

        $user = $this->createUser();

        $this->authService->initiateLogin($user->credential->email, 'Test@12345');

        Mail::assertSent(\App\Mail\OtpMail::class);
    }

    /** @test */
    public function it_verifies_otp_and_issues_tokens()
    {
        $user = $this->createUser([], ['email' => 'test@example.com']);
        $credential = $user->credential;

        $otp = $this->generateOtpFor($credential, 10);

        $result = $this->authService->verifyOtpAndIssueTokens('test@example.com', $otp);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('Bearer', $result['token_type']);
    }

    /** @test */
    public function it_includes_user_data_in_token_response()
    {
        $user = $this->createUser(
            ['first_name' => 'John', 'last_name' => 'Doe'],
            ['email' => 'john@example.com']
        );
        $credential = $user->credential;

        $otp = $this->generateOtpFor($credential, 10);

        $result = $this->authService->verifyOtpAndIssueTokens('john@example.com', $otp);

        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('John', $result['user']['first_name']);
        $this->assertEquals('Doe', $result['user']['last_name']);
        $this->assertEquals($user->id, $result['user']['id']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_otp()
    {
        $this->expectException(InvalidOtpException::class);
        $this->expectExceptionMessage('Invalid OTP code');

        $user = $this->createUser([], ['email' => 'test@example.com']);
        $credential = $user->credential;

        $this->generateOtpFor($credential, 10);

        $this->authService->verifyOtpAndIssueTokens('test@example.com', '9999');
    }

    /** @test */
    public function it_throws_exception_for_expired_otp()
    {
        $this->expectException(OtpExpiredException::class);
        $this->expectExceptionMessage('OTP has expired');

        $user = $this->createUser([], ['email' => 'test@example.com']);
        $credential = $user->credential;

        $otp = $this->generateOtpFor($credential, 10);
        $this->expireOtp($credential);

        $this->authService->verifyOtpAndIssueTokens('test@example.com', $otp);
    }

    /** @test */
    public function it_throws_exception_when_no_otp_generated()
    {
        $this->expectException(InvalidOtpException::class);
        $this->expectExceptionMessage('No OTP has been generated');

        $user = $this->createUser([], ['email' => 'test@example.com']);

        // Try to verify OTP without generating one first
        $this->authService->verifyOtpAndIssueTokens('test@example.com', '1234');
    }

    /** @test */
    public function it_clears_otp_after_successful_verification()
    {
        $user = $this->createUser([], ['email' => 'test@example.com']);
        $credential = $user->credential;

        $otp = $this->generateOtpFor($credential, 10);

        $this->authService->verifyOtpAndIssueTokens('test@example.com', $otp);

        $credential->refresh();
        $this->assertNull($credential->otp);
        $this->assertNull($credential->otp_expiry);
    }

    /** @test */
    public function it_revokes_refresh_token_on_logout()
    {
        $user = $this->createUser();
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        $this->authService->logout($refreshToken);

        $this->assertDatabaseHas('refresh_tokens', [
            'token' => $refreshToken,
            'is_revoked' => true,
        ]);
    }

    /** @test */
    public function it_formats_user_response_correctly()
    {
        $user = $this->createUser(
            ['first_name' => 'Jane', 'last_name' => 'Smith'],
            ['email' => 'jane@example.com', 'username' => 'janesmith']
        );
        $credential = $user->credential;

        $otp = $this->generateOtpFor($credential, 10);

        $result = $this->authService->verifyOtpAndIssueTokens('jane@example.com', $otp);

        $userData = $result['user'];
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('first_name', $userData);
        $this->assertArrayHasKey('last_name', $userData);
        $this->assertArrayHasKey('role', $userData);
        $this->assertArrayHasKey('email', $userData);
        $this->assertArrayHasKey('username', $userData);

        // Ensure sensitive data is NOT included
        $this->assertArrayNotHasKey('password', $userData);
        $this->assertArrayNotHasKey('otp', $userData);
    }

    /** @test */
    public function it_enforces_rate_limiting_during_login()
    {
        $this->expectException(\App\Exceptions\OtpRateLimitException::class);

        Mail::fake();

        $user = $this->createUser([], ['email' => 'test@example.com']);

        // First login - should succeed
        $this->authService->initiateLogin('test@example.com', 'Test@12345');

        // Second login immediately - should fail due to rate limiting
        $this->authService->initiateLogin('test@example.com', 'Test@12345');
    }
}
