<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

class DevController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * One-click login for development.
     * ONLY works in local environment.
     */
    public function loginAs(string $email): JsonResponse
    {
        if (App::environment('production')) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $credential = Credential::where('email', $email)->with('user.role')->first();

        if (!$credential) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $tokens = $this->authService->issueTokens($credential->user, false);

        return response()->json([
            'success' => true,
            'message' => 'Dev login successful',
            'data' => [
                'user' => [
                    'id' => $credential->user->id,
                    'email' => $credential->email,
                    'name' => $credential->user->full_name,
                    'role' => $credential->user->role->name,
                ],
                'tokens' => $tokens,
            ],
        ]);
    }
}
