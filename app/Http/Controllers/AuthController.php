<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTokenException;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
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

    /**
     * Register new user (public registration - creates regular users only)
     */
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

        // Public registration always creates regular users
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
                'email' => $user->credential->email,
            ],
        ], 201);
    }

    /**
     * Step 1: Login with username/email + password
     */
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
                'email' => $credential->email,
                'next_step' => 'verify_otp',
            ],
        ]);
    }

    /**
     * Step 2: Verify OTP and issue tokens
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $tokens = $this->authService->verifyOtpAndIssueTokens(
            $request->identifier,
            $request->otp,
            $request->has('remember_me') ? $request->boolean('remember_me') : null
        );

        $refreshToken = $tokens['refresh_token'];
        $refreshTokenExpiryMinutes = $tokens['refresh_expires_in'] / 60;
        unset($tokens['refresh_token']);
        unset($tokens['refresh_expires_in']);

        $isSecure = str_starts_with(config('app.url', ''), 'https');

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => $tokens,
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

    /**
     * Refresh access token
     */
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
            'data' => $tokens,
        ]);
    }

    /**
     * Logout (revoke refresh token)
     */
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
