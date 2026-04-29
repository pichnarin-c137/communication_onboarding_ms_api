<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTokenException;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private UserService $userService,
        private JwtService $jwtService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $userData = $request->only([
            'first_name',
            'last_name',
            'dob',
            'address',
            'gender',
            'nationality',
        ]);

        $userData['role'] = 'user';

        $credentialData = $request->only([
            'email',
            'username',
            'phone_number',
            'password',
        ]);

        $user = $this->userService->createUser($userData, $credentialData);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully. OTP sent to email.',
            'data' => [
                'user_id' => $user->id,
                'email'   => $user->credential->email,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credential = $this->authService->initiateLogin(
            $request->identifier,
            $request->password,
            $request->boolean('remember_me')
        );

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email',
            'data' => [
                'email'     => $credential->email,
                'next_step' => 'verify_otp',
            ],
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $tokens = $this->authService->verifyOtpAndIssueTokens(
            $request->identifier,
            $request->otp,
            $request->has('remember_me') ? $request->boolean('remember_me') : null,
            $request->input('timezone')
        );

        $refreshToken              = $tokens['refresh_token'];
        $refreshTokenExpiryMinutes = $tokens['refresh_expires_in'] / 60;
        unset($tokens['refresh_token'], $tokens['refresh_expires_in']);

        $isSecure = str_starts_with(config('app.url', ''), 'https');

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => $tokens,
        ])->cookie(
            'refresh_token',
            $refreshToken,
            $refreshTokenExpiryMinutes,
            '/api/v1/auth',
            null,
            $isSecure,
            true,
            false,
            $isSecure ? 'None' : 'Lax'
        );
    }

    // -------------------------------------------------------------------------
    // Forgot password (unauthenticated)
    // -------------------------------------------------------------------------

    /**
     * Step 1 — user submits their email; a reset link is sent if found.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->email);

        return response()->json([
            'success' => true,
            'message' => 'If that email is registered, a password reset link has been sent.',
        ]);
    }

    /**
     * Step 2 — user submits the token from the email + a new password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(
            $request->email,
            $request->token,
            $request->password,
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now log in with your new password.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Change password (authenticated — user is already logged in)
    // -------------------------------------------------------------------------

    /**
     * User provides their current password plus the desired new password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->get('auth_user_id'),
            $request->old_password,
            $request->password,
        );

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Token management
    // -------------------------------------------------------------------------

    public function refreshToken(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');

        if (! $refreshToken) {
            throw new InvalidTokenException('No refresh token provided.');
        }

        $tokens = $this->jwtService->refreshAccessToken($refreshToken);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data'    => $tokens,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');

        if ($refreshToken) {
            $this->authService->logout($refreshToken);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ])->withoutCookie('refresh_token', '/api/v1/auth');
    }
}
