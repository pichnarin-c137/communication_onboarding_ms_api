<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserSettingsRequest;
use App\Services\UserSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    public function __construct(
        private UserSettingsService $userSettingsService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $userId   = $request->get('auth_user_id');
        $settings = $this->userSettingsService->getSettings($userId);

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ]);
    }

    public function update(UpdateUserSettingsRequest $request): JsonResponse
    {
        $userId = $request->get('auth_user_id');
        $data   = $request->validated();

        $settings = $this->userSettingsService->updateSettings($userId, $data);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data'    => $settings,
        ]);
    }
}
