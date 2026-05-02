<?php

namespace App\Http\Controllers;

use App\Exceptions\Business\FeedbackAlreadySubmittedException;
use App\Http\Requests\Appointment\SubmitAppointmentFeedbackRequest;
use App\Services\Appointment\AppointmentFeedbackService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

class AppointmentFeedbackController extends Controller
{
    public function __construct(
        private readonly AppointmentFeedbackService $feedbackService,
    ) {}

    public function showForm(string $token): Response
    {
        $context = $this->feedbackService->getFormContext($token);

        return response(view('feedback.appointment_form', array_merge(
            $context,
            ['errors' => new ViewErrorBag]
        )));
    }

    public function submitViaForm(
        SubmitAppointmentFeedbackRequest $request,
        string $token
    ): Response|View {
        $emptyErrors = new ViewErrorBag;

        try {
            $this->feedbackService->submit($token, $request->validated());

            return response(view('feedback.appointment_form', [
                'state' => 'success',
                'appointment' => null,
                'errors' => $emptyErrors,
            ]));
        } catch (FeedbackAlreadySubmittedException $e) {
            return response(view('feedback.appointment_form', [
                'state' => 'already_submitted',
                'appointment' => null,
                'errors' => $emptyErrors,
            ]));
        } catch (ValidationException $e) {
            $tokenError = $e->errors()['token'][0] ?? '';
            $state = str_contains($tokenError, 'expired') ? 'expired' : 'invalid';

            return response(view('feedback.appointment_form', [
                'state' => $state,
                'appointment' => null,
                'errors' => $emptyErrors,
            ]));
        }
    }

    public function index(string $id): JsonResponse
    {
        $feedbacks = $this->feedbackService->getForAppointment($id);

        return response()->json([
            'success' => true,
            'data' => $feedbacks,
        ]);
    }
}
