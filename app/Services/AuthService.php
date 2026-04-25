<?php

namespace App\Services;

use App\Exceptions\AccountSuspendedException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\InvalidOtpException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\MailDeliveryException;
use App\Exceptions\OtpExpiredException;
use App\Exceptions\OtpRateLimitException;
use App\Exceptions\TokenExpiredException;
use App\Exceptions\WrongEmailRegexFormat;
use App\Mail\ForgotPasswordMail;
use App\Models\Credential;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Random\RandomException;

class AuthService
{
    public function __construct(
        private JwtService $jwtService,
        private OtpService $otpService
    ) {}

    /**
     * Step 1: Authenticate user with username/email and password
     * Returns credential if successful, triggers OTP send
     *
     * @throws AccountSuspendedException
     * @throws InvalidCredentialsException
     * @throws OtpRateLimitException
     * @throws MailDeliveryException
     */
    public function initiateLogin(string $identifier, string $password, bool $rememberme = false): Credential
    {
        // Find credential by email or username
        $credential = Credential::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->with(['user.role', 'user.credential'])
            ->first();

        if (! $credential) {
            throw new InvalidCredentialsException('Invalid username or password');
        }

        // Verify password
        if (! Hash::check($password, $credential->password)) {
            throw new InvalidCredentialsException('Invalid username or password');
        }

        // Check if user is suspended
        if ($credential->user->isSuspended()) {
            throw new AccountSuspendedException('Your account has been suspended');
        }

        // Persist remember_me choice between login and verify-otp steps.
        if ($credential->remember_me !== $rememberme) {
            $credential->remember_me = $rememberme;
            $credential->save();
        }

        // Check rate limiting before sending OTP
        $this->otpService->canResendOtp($credential);

        // Generate and send OTP
        $this->otpService->sendOtp($credential);

        return $credential;
    }

    /**
     * Step 2: Verify OTP and issue tokens
     *
     * @throws InvalidOtpException
     */
    public function verifyOtpAndIssueTokens(string $identifier, string $otp, ?bool $rememberme = null): array
    {
        $credential = Credential::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->with(['user.role', 'user.credential'])
            ->first();

        if (! $credential) {
            throw new InvalidCredentialsException('Invalid credentials');
        }

        // Check if OTP exists
        if (! $credential->otp) {
            throw new InvalidOtpException('No OTP has been generated. Please login first.');
        }

        // Check if OTP is expired
        if (Carbon::now()->greaterThan($credential->otp_expiry)) {
            throw new OtpExpiredException('OTP has expired. Please request a new one.');
        }

        // Verify OTP
        if (! $this->otpService->verifyOtp($credential, $otp)) {
            throw new InvalidOtpException('Invalid OTP code');
        }

        $user = $credential->user;
        $resolvedRememberMe = $rememberme ?? (bool) $credential->remember_me;

        return $this->issueTokens($user, $resolvedRememberMe);
    }

    /**
     * Generate tokens for a user (bypass OTP/Password)
     */
    public function issueTokens(User $user, bool $rememberme = false): array
    {
        $accessExpiryMinutes = $rememberme
            ? (int) config('jwt.rememberme_access_token_expiry', 1440)
            : (int) config('jwt.access_token_expiry', 60);

        $refreshExpiryMinutes = $rememberme
            ? (int) config('jwt.rememberme_refresh_token_expiry', 43200)
            : (int) config('jwt.refresh_token_expiry', 1440);

        $accessToken = $this->jwtService->generateAccessToken($user, $accessExpiryMinutes);
        $refreshToken = $this->jwtService->generateRefreshToken($user, $refreshExpiryMinutes, $rememberme);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessExpiryMinutes * 60,
            'refresh_expires_in' => $refreshExpiryMinutes * 60,
            'user' => $this->formatUserResponse($user),
        ];
    }

    // Forgot password (unauthenticated)

    /**
     * Step 1: Generate a reset token and email a reset link.
     * Always returns silently when the email is not found (prevent enumeration).
     *
     * @throws RandomException
     */
    public function forgotPassword(string $email): void
    {
        if (! preg_match('/^[\w\.\-]+@([\w\-]+\.)+[a-zA-Z]{2,}$/', $email)) {
            throw new WrongEmailRegexFormat('The provided email does not match the required format.');
        }

        $credential = Credential::where('email', $email)->first();

        if (! $credential) {
            return;
        }

        $rawToken = bin2hex(random_bytes(32));
        $ttlMinutes = (int) config('coms.password_reset_ttl_minutes', 60);
        $resetLink = url('/reset-password').'?token='.$rawToken.'&email='.urlencode($email);

        $credential->update([
            'reset_token' => hash('sha256', $rawToken),
            'reset_token_expires_at' => Carbon::now()->addMinutes($ttlMinutes),
        ]);

        try {
            Mail::to($email)->queue(new ForgotPasswordMail($resetLink, $ttlMinutes));
        } catch (\Throwable $e) {
            Log::error('Failed to queue password reset email', ['email' => $email, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Step 2: Verify reset token and set the new password.
     */
    public function resetPassword(string $email, string $plainToken, string $newPassword): void
    {
        $credential = Credential::where('email', $email)->first();

        if (! $credential || ! $credential->reset_token) {
            throw new InvalidTokenException('Invalid or expired password reset token.');
        }

        if (Carbon::now()->greaterThan($credential->reset_token_expires_at)) {
            $credential->update(['reset_token' => null, 'reset_token_expires_at' => null]);
            throw new TokenExpiredException('Password reset link has expired. Please request a new one.');
        }

        if (! hash_equals(hash('sha256', $plainToken), $credential->reset_token)) {
            throw new InvalidTokenException('Invalid or expired password reset token.');
        }

        $credential->update([
            'password' => $newPassword,
            'reset_token' => null,
            'reset_token_expires_at' => null,
        ]);
    }

    // Change password (authenticated — user is logged in)

    /**
     * Verify the current password and update to the new one.
     */
    public function changePassword(string $userId, string $oldPassword, string $newPassword): void
    {
        $credential = Credential::where('user_id', $userId)->firstOrFail();

        if (! Hash::check($oldPassword, $credential->password)) {
            throw new InvalidCredentialsException('Current password is incorrect.');
        }

        $credential->update(['password' => $newPassword]);

        $this->jwtService->revokeAllUserTokens($userId);
    }

    /**
     * Logout user by revoking refresh token
     */
    public function logout(string $refreshToken): void
    {
        $this->jwtService->revokeRefreshToken($refreshToken);
    }

    /**
     * Format user response (exclude sensitive data)
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'dob' => $user->dob->format('Y-m-d'),
            'address' => $user->address,
            'gender' => $user->gender,
            'nationality' => $user->nationality,
            'role' => $user->role->role,
            'email' => $user->credential->email,
            'username' => $user->credential->username,
            'phone_number' => $user->credential->phone_number,
        ];
    }
}
