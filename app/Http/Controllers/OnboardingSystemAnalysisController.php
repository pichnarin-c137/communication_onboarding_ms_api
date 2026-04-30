<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOnboardingSystemAnalysisRequest;
use App\Models\OnboardingRequest;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingSystemAnalysisController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService
    ) {}

    public function show(string $onboardingId): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($onboardingId);
        $analysis = $this->onboardingService->getSystemAnalysis($onboarding);

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    public function update(UpdateOnboardingSystemAnalysisRequest $request, string $onboardingId): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($onboardingId);
        $analysis = $this->onboardingService->getSystemAnalysis($onboarding);

        $updated = $this->onboardingService->updateSystemAnalysis($analysis, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'System analysis updated.',
            'data' => $updated,
        ]);
    }
}
