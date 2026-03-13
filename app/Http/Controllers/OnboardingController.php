<?php

namespace App\Http\Controllers;

use App\Models\OnboardingRequest;
use App\Models\User;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private OnboardingService $onboardingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->get('auth_user_id'));
        $filters = $request->only(['status']);
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));

        $result = $this->onboardingService->list($user, $filters, $perPage, $page);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $onboarding = $this->onboardingService->get($id);

        return response()->json([
            'success' => true,
            'data' => $onboarding,
        ]);
    }

    public function refreshProgress(string $id): JsonResponse
    {
        $onboarding = $this->onboardingService->refreshProgress($id);

        return response()->json([
            'success' => true,
            'message' => 'Progress refreshed.',
            'data' => ['progress_percentage' => $onboarding->progress_percentage],
        ]);
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $trainer = User::findOrFail($request->get('auth_user_id'));

        $this->onboardingService->complete($onboarding, $trainer);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding marked as completed.',
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);

        $this->onboardingService->cancel($onboarding, $request->input('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Onboarding cancelled.',
        ]);
    }
}
