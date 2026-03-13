<?php

namespace App\Services;

use App\Exceptions\MailDeliveryException;
use App\Exceptions\OtpRateLimitException;
use App\Mail\OtpMail;
use App\Models\Credential;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    private int $otpLength;

    private int $otpExpiryMin;

    private int $otpExpiryMax;

    public function __construct()
    {
        $this->otpLength = config('otp.length', 4);
        $this->otpExpiryMin = config('otp.expiry_min', 5);
        $this->otpExpiryMax = config('otp.expiry_max', 10);
    }

    /**
     * Generate numeric OTP
     */
    public function generateOtp(): string
    {
        $min = pow(10, $this->otpLength - 1);
        $max = pow(10, $this->otpLength) - 1;

        return (string) random_int($min, $max);
    }

    /**
     * Generate and send OTP to user
     */
    public function sendOtp(Credential $credential): void
    {
        $otp = $this->generateOtp();
        $expiryMinutes = random_int($this->otpExpiryMin, $this->otpExpiryMax);

        try {

            // Store OTP in database first
            $credential->update([
                'otp' => $otp,
                'otp_expiry' => Carbon::now()->addMinutes($expiryMinutes),
            ]);

            // Attempt to send email
            Mail::to($credential->email)->send(new OtpMail($otp, $expiryMinutes));

        } catch (MailDeliveryException $e) {
            // Rollback OTP on mail failure
            $credential->clearOtp();
            throw $e;
        } catch (\Throwable $e) {
            // Log and rollback OTP on any other failure
            Log::error('Failed to send OTP email', [
                'email' => $credential->email,
                'error' => $e->getMessage(),
            ]);

            // Rollback OTP on mail failure
            $credential->clearOtp();
            throw new MailDeliveryException('Failed to send OTP email: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Credential $credential, string $otp): bool
    {
        if (! $credential->hasValidOtp($otp)) {
            return false;
        }

        // Clear OTP after successful verification
        $credential->clearOtp();

        return true;
    }

    /**
     * Check if can resend OTP (rate limiting)
     * Throws exception if rate limit is exceeded
     */
    public function canResendOtp(Credential $credential): bool
    {
        if (! $credential->otp_expiry) {
            return true;
        }

        // Allow resend only if OTP has expired or 1 minute has passed
        $oneMinuteAgo = Carbon::now()->subMinute();
        $canResend = Carbon::now()->greaterThan($credential->otp_expiry) ||
                     $credential->updated_at->lessThan($oneMinuteAgo);

        if (! $canResend) {
            throw new OtpRateLimitException('Please wait at least 1 minute before requesting another OTP');
        }

        return true;
    }
}
