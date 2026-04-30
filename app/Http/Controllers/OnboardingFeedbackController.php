<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\SubmitEmailFeedbackRequest;
use App\Http\Requests\Onboarding\SubmitManualFeedbackRequest;
use App\Models\OnboardingFeedbackToken;
use App\Models\OnboardingRequest;
use App\Services\Onboarding\OnboardingFeedbackService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Random\RandomException;

class OnboardingFeedbackController extends Controller
{
    public function __construct(
        private readonly OnboardingFeedbackService $feedbackService,
    ) {}

    /**
     * POST /onboarding/{id}/feedback/request
     * Trainer or admin triggers an email to the client with a feedback link.
     *
     * @throws RandomException
     */
    public function request(string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $this->feedbackService->requestFeedback($onboarding);

        return response()->json([
            'success' => true,
            'message' => 'Feedback request email sent to the client.',
        ]);
    }

    /**
     * POST /onboarding/{id}/feedback
     * Trainer submits manual feedback on behalf of the client.
     */
    public function submitManual(SubmitManualFeedbackRequest $request, string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $userId = $request->get('auth_user_id');

        $feedback = $this->feedbackService->submitManual(
            $onboarding,
            (int) $request->validated('rating'),
            $request->validated('comment'),
            $userId
        );

        return response()->json([
            'success' => true,
            'message' => 'Feedback submitted.',
            'data' => $feedback,
        ]);
    }

    /**
     * GET /onboarding/{id}/feedback
     * Read the submitted feedback for an onboarding.
     */
    public function show(string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $feedback = $this->feedbackService->getFeedback($onboarding->load('clientFeedback', 'feedbackToken'));

        return response()->json([
            'success' => true,
            'data' => $feedback,
        ]);
    }

    /**
     * GET /feedback/{token}
     * Public — renders the Blade feedback form for the client.
     */
    public function showForm(string $token): Response
    {
        $hashedToken = hash('sha256', $token);
        $tokenRecord = OnboardingFeedbackToken::where('token', $hashedToken)
            ->with('onboarding')
            ->first();

        $emptyErrors = new ViewErrorBag;

        if (! $tokenRecord) {
            return response(view('feedback.form', [
                'state' => 'expired',
                'onboarding' => null,
                'errors' => $emptyErrors,
            ]));
        }

        if ($tokenRecord->used_at !== null) {
            return response(view('feedback.form', [
                'state' => 'used',
                'onboarding' => $tokenRecord->onboarding,
                'errors' => $emptyErrors,
            ]));
        }

        if ($tokenRecord->expires_at->isPast()) {
            return response(view('feedback.form', [
                'state' => 'expired',
                'onboarding' => $tokenRecord->onboarding,
                'errors' => $emptyErrors,
            ]));
        }

        return response(view('feedback.form', [
            'state' => 'form',
            'onboarding' => $tokenRecord->onboarding,
            'token' => $token,
            'errors' => $emptyErrors,
        ]));
    }

    /**
     * POST /feedback/{token}
     * Public — client submits their rating via the email link.
     */
    public function submitViaEmail(SubmitEmailFeedbackRequest $request, string $token): View|Response
    {
        $emptyErrors = new ViewErrorBag;

        try {
            $this->feedbackService->submitViaEmail(
                $token,
                (int) $request->validated('rating'),
                $request->validated('comment')
            );

            return response(view('feedback.form', [
                'state' => 'success',
                'onboarding' => null,
                'errors' => $emptyErrors,
            ]));
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $tokenError = $errors['token'][0] ?? null;

            $state = match (true) {
                str_contains((string) $tokenError, 'expired') => 'expired',
                str_contains((string) $tokenError, 'already been used') => 'used',
                default => 'expired',
            };

            return response(view('feedback.form', [
                'state' => $state,
                'onboarding' => null,
                'errors' => $emptyErrors,
            ]));
        }
    }
}
