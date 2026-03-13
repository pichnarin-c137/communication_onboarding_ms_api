<?php

namespace Tests\Unit\Services;

use App\Exceptions\OtpRateLimitException;
use App\Mail\OtpMail;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otpService = app(OtpService::class);
    }

    /** @test */
    public function it_generates_four_digit_otp()
    {
        $otp = $this->otpService->generateOtp();

        $this->assertIsString($otp);
        $this->assertEquals(4, strlen($otp));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $otp);
    }

    /** @test */
    public function it_generates_numeric_otp()
    {
        $otp = $this->otpService->generateOtp();

        $this->assertTrue(ctype_digit($otp));
        $this->assertGreaterThanOrEqual(1000, (int) $otp);
        $this->assertLessThanOrEqual(9999, (int) $otp);
    }

    /** @test */
    public function it_stores_otp_in_database_when_sending()
    {
        Mail::fake();

        $user = $this->createUser();
        $credential = $user->credential;

        $this->otpService->sendOtp($credential);

        $credential->refresh();
        $this->assertNotNull($credential->otp);
        $this->assertNotNull($credential->otp_expiry);
        $this->assertEquals(4, strlen($credential->otp));
    }

    /** @test */
    public function it_sets_otp_expiry_within_configured_range()
    {
        Mail::fake();

        $user = $this->createUser();
        $credential = $user->credential;

        $beforeSend = Carbon::now();
        $this->otpService->sendOtp($credential);
        $afterSend = Carbon::now();

        $credential->refresh();

        // OTP expiry should be between 5-10 minutes from now (as configured)
        // Add 1 second buffer for execution time and timing precision
        $minExpiry = $beforeSend->copy()->addMinutes(5)->subSecond();
        $maxExpiry = $afterSend->copy()->addMinutes(10)->addSecond();

        $this->assertTrue($credential->otp_expiry->greaterThanOrEqualTo($minExpiry));
        $this->assertTrue($credential->otp_expiry->lessThanOrEqualTo($maxExpiry));
    }

    /** @test */
    public function it_sends_email_to_credential_email()
    {
        Mail::fake();

        $user = $this->createUser();
        $credential = $user->credential;

        $this->otpService->sendOtp($credential);

        Mail::assertSent(OtpMail::class, function ($mail) use ($credential) {
            return $mail->hasTo($credential->email);
        });
    }

    /** @test */
    public function it_verifies_correct_otp()
    {
        $user = $this->createUser();
        $credential = $user->credential;

        $otp = $this->generateOtpFor($credential, 10);

        $result = $this->otpService->verifyOtp($credential, $otp);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_incorrect_otp()
    {
        $user = $this->createUser();
        $credential = $user->credential;

        $this->generateOtpFor($credential, 10);

        $result = $this->otpService->verifyOtp($credential, '9999');

        $this->assertFalse($result);
    }

    /** @test */
    public function it_clears_otp_after_successful_verification()
    {
        $user = $this->createUser();
        $credential = $user->credential;

        $otp = $this->generateOtpFor($credential, 10);

        $this->otpService->verifyOtp($credential, $otp);

        $credential->refresh();
        $this->assertNull($credential->otp);
        $this->assertNull($credential->otp_expiry);
    }

    /** @test */
    public function it_rejects_expired_otp()
    {
        $user = $this->createUser();
        $credential = $user->credential;

        $otp = $this->generateOtpFor($credential, 10);
        $this->expireOtp($credential);

        $result = $this->otpService->verifyOtp($credential, $otp);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_allows_otp_resend_when_no_otp_exists()
    {
        $user = $this->createUser();
        $credential = $user->credential;

        $canResend = $this->otpService->canResendOtp($credential);

        $this->assertTrue($canResend);
    }

    /** @test */
    public function it_throws_exception_when_resending_too_quickly()
    {
        $this->expectException(OtpRateLimitException::class);
        $this->expectExceptionMessage('Please wait at least 1 minute');

        Mail::fake();

        $user = $this->createUser();
        $credential = $user->credential;

        // Send OTP
        $this->otpService->sendOtp($credential);

        // Try to resend immediately (within 1 minute)
        $this->otpService->canResendOtp($credential);
    }

    /** @test */
    public function it_allows_otp_resend_after_one_minute()
    {
        Mail::fake();

        $user = $this->createUser();
        $credential = $user->credential;

        // Send OTP
        $this->otpService->sendOtp($credential);

        // Move time forward by more than 1 minute
        Carbon::setTestNow(Carbon::now()->addMinutes(2));

        $canResend = $this->otpService->canResendOtp($credential);

        $this->assertTrue($canResend);

        Carbon::setTestNow(); // Reset time
    }

    /** @test */
    public function it_allows_otp_resend_after_otp_expires()
    {
        $user = $this->createUser();
        $credential = $user->credential;

        $this->generateOtpFor($credential, 10);
        $this->expireOtp($credential);

        $canResend = $this->otpService->canResendOtp($credential);

        $this->assertTrue($canResend);
    }

    /** @test */
    public function it_includes_expiry_minutes_in_email()
    {
        Mail::fake();

        $user = $this->createUser();
        $credential = $user->credential;

        $this->otpService->sendOtp($credential);

        Mail::assertSent(OtpMail::class, function ($mail) {
            // The OtpMail should have expiryMinutes property
            return property_exists($mail, 'expiryMinutes') &&
                   $mail->expiryMinutes >= 5 &&
                   $mail->expiryMinutes <= 10;
        });
    }
}
