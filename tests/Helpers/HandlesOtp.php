<?php

namespace Tests\Helpers;

use App\Models\Credential;
use Carbon\Carbon;

trait HandlesOtp
{
    /**
     * Generate and set OTP for a credential
     */
    protected function generateOtpFor(Credential $credential, int $expiryMinutes = 10): string
    {
        $otp = '1234'; // Fixed OTP for testing

        $credential->update([
            'otp' => $otp,
            'otp_expiry' => Carbon::now()->addMinutes($expiryMinutes),
        ]);

        return $otp;
    }

    /**
     * Set a custom OTP for a credential
     */
    protected function setOtp(Credential $credential, string $otp, int $expiryMinutes = 10): void
    {
        $credential->update([
            'otp' => $otp,
            'otp_expiry' => Carbon::now()->addMinutes($expiryMinutes),
        ]);
    }

    /**
     * Expire the OTP for a credential
     */
    protected function expireOtp(Credential $credential): void
    {
        $credential->update([
            'otp_expiry' => Carbon::now()->subMinutes(5),
        ]);
    }

    /**
     * Clear OTP from a credential
     */
    protected function clearOtp(Credential $credential): void
    {
        $credential->clearOtp();
    }

    /**
     * Get the latest OTP from a credential
     */
    protected function getOtp(Credential $credential): ?string
    {
        return $credential->fresh()->otp;
    }
}
