<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOnboardingLessonRequest;
use App\Http\Requests\UpdateOnboardingLessonRequest;
use App\Models\OnboardingRequest;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingLessonController extends Controller
{
    public function __construct(
        private OnboardingService $onboardingService
    ) {}

    public function index(string $onboardingId): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($onboardingId);
        $lessons = $this->onboardingService->listLessons($onboarding);

        return response()->json([
            'success' => true,
            'data'    => $lessons,
        ]);
    }

    public function store(CreateOnboardingLessonRequest $request, string $onboardingId): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($onboardingId);
        $lesson = $this->onboardingService->addLesson($onboarding, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lesson added.',
            'data'    => $lesson,
        ], 201);
    }

    public function update(UpdateOnboardingLessonRequest $request, string $onboardingId, string $lessonId): JsonResponse
    {
        $lesson = OnboardingRequest::findOrFail($onboardingId)->lessons()->findOrFail($lessonId);

        $updated = $this->onboardingService->updateLesson($lesson, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lesson updated.',
            'data'    => $updated,
        ]);
    }

    public function destroy(string $onboardingId, string $lessonId): JsonResponse
    {
        $lesson = OnboardingRequest::findOrFail($onboardingId)->lessons()->findOrFail($lessonId);

        $this->onboardingService->deleteLesson($lesson);

        return response()->json([
            'success' => true,
            'message' => 'Lesson deleted.',
        ]);
    }

    public function send(Request $request, string $onboardingId, string $lessonId): JsonResponse
    {
        $lesson = OnboardingRequest::findOrFail($onboardingId)->lessons()->findOrFail($lessonId);

        $this->onboardingService->sendLesson($lesson, $request->get('auth_user_id'));

        return response()->json([
            'success' => true,
            'message' => 'Lesson sent successfully.',
        ]);
    }
}
