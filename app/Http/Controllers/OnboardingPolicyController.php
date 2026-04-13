<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOnboardingPolicyRequest;
use App\Models\OnboardingRequest;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingPolicyController extends Controller
{
    public function __construct(
        private OnboardingService $onboardingService
    ) {}

    public function index(string $onboardingId): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($onboardingId);
        $policies = $this->onboardingService->listPolicies($onboarding);

        return response()->json([
            'success' => true,
            'data' => $policies,
        ]);
    }

    public function store(CreateOnboardingPolicyRequest $request, string $onboardingId): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($onboardingId);
        $policy = $this->onboardingService->addPolicy($onboarding, $request->input('policy_name'));

        return response()->json([
            'success' => true,
            'message' => 'Policy added.',
            'data' => $policy,
        ], 201);
    }

    public function check(Request $request, string $onboardingId, string $policyId): JsonResponse
    {
        $policy = OnboardingRequest::findOrFail($onboardingId)->policies()->findOrFail($policyId);

        $updated = $this->onboardingService->checkPolicy($policy, $request->get('auth_user_id'));

        return response()->json([
            'success' => true,
            'message' => 'Policy checked.',
            'data' => $updated,
        ]);
    }

    public function uncheck(Request $request, string $onboardingId, string $policyId): JsonResponse
    {
        $policy = OnboardingRequest::findOrFail($onboardingId)->policies()->findOrFail($policyId);

        $updated = $this->onboardingService->uncheckPolicy($policy, $request->get('auth_user_id'));

        return response()->json([
            'success' => true,
            'message' => 'Policy unchecked.',
            'data' => $updated,
        ]);
    }

    public function destroy(string $onboardingId, string $policyId): JsonResponse
    {
        $policy = OnboardingRequest::findOrFail($onboardingId)->policies()->findOrFail($policyId);

        $this->onboardingService->removePolicy($policy);

        return response()->json([
            'success' => true,
            'message' => 'Policy removed.',
        ]);
    }
}
