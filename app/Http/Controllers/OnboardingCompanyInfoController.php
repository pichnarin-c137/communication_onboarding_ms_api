<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOnboardingCompanyInfoRequest;
use App\Models\OnboardingRequest;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingCompanyInfoController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService
    ) {}

    public function show(string $onboardingId): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($onboardingId);
        $info = $this->onboardingService->getCompanyInfo($onboarding);

        return response()->json([
            'success' => true,
            'data' => $info,
        ]);
    }

    public function update(UpdateOnboardingCompanyInfoRequest $request, string $onboardingId): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($onboardingId);
        $info = $this->onboardingService->getCompanyInfo($onboarding);

        $updated = $this->onboardingService->updateCompanyInfo(
            $info,
            $request->validated(),
            $request->get('auth_user_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Company info updated.',
            'data' => $updated,
        ]);
    }
}
