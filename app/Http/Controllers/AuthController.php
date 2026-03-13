<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

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
            'first_name', 'last_name', 'dob', 'address', 'gender', 'nationality',
        ]);

        // Public registration always creates regular users
        $userData['role'] = 'user';

        $credentialData = $request->only([
            'email', 'username', 'phone_number', 'password',
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
            $request->password
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
            $request->otp
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => $tokens,
        ]);
    }

    /**
     * Refresh access token
     */
    public function refreshToken(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->jwtService->refreshAccessToken($request->refresh_token);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => $tokens,
        ]);
    }

    /**
     * Logout (revoke refresh token)
     */
    public function logout(RefreshTokenRequest $request): JsonResponse
    {
        $this->authService->logout($request->refresh_token);

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}
