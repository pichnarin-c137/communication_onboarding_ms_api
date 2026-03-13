<?php

namespace App\Http\Controllers;

use App\Services\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleOAuthController extends Controller
{
    public function __construct(
        private GoogleOAuthService $googleOAuthService
    ) {}

    public function redirect(Request $request): JsonResponse
    {
        $userId = $request->auth_user_id;

        $redirectUrl = $this->googleOAuthService->getRedirectUrl($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'redirect_url' => $redirectUrl,
            ],
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        $userId = $request->state;

        $frontendUrl = config('services.google.frontend_redirect_url');

        try {
            $this->googleOAuthService->handleCallback($request->code, $userId);

            return redirect()->to($frontendUrl.'?google=connected');
        } catch (\Exception $e) {
            Log::error('Google OAuth callback failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return redirect()->to($frontendUrl.'?google=failed&error='.urlencode($e->getMessage()));
        }
    }

    public function status(Request $request): JsonResponse
    {
        $userId = $request->auth_user_id;

        $status = $this->googleOAuthService->getStatus($userId);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $userId = $request->auth_user_id;

        $this->googleOAuthService->disconnect($userId);

        return response()->json([
            'success' => true,
            'message' => 'Google account disconnected successfully',
        ]);
    }
}
