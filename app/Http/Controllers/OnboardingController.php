<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\HoldOnboardingRequest;
use App\Http\Requests\Onboarding\ReassignTrainerRequest;
use App\Http\Requests\Onboarding\RequestRevisionRequest;
use App\Http\Requests\Onboarding\SetDueDateRequest;
use App\Models\OnboardingRequest;
use App\Models\OnboardingTrainerAssignment;
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

    public function sales(string $id): JsonResponse
    {
        $onboarding = $this->onboardingService->getClientSales($id);

        $creator = $onboarding->appointment?->creator;

        return response()->json([
            'success' => true,
            'data' => [
                'sale' => $creator ? [
                    'id' => $creator->id,
                    'first_name' => $creator->first_name,
                    'last_name' => $creator->last_name,
                ] : null,
                'sales' => $onboarding->client?->sales?->sortByDesc('created_at')->values() ?? [],
            ],
        ]);
    }

    public function start(string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $this->onboardingService->start($onboarding);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding started.',
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

    // --- New status transition endpoints ---

    public function hold(HoldOnboardingRequest $request, string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $userId = $request->get('auth_user_id');

        $this->onboardingService->hold($onboarding, $request->validated('reason'), $userId);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding put on hold.',
        ]);
    }

    public function resumeHold(Request $request, string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $userId = $request->get('auth_user_id');

        $this->onboardingService->resumeHold($onboarding, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding resumed.',
        ]);
    }

    public function requestRevision(RequestRevisionRequest $request, string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $userId = $request->get('auth_user_id');

        $this->onboardingService->requestRevision($onboarding, $request->validated('note'), $userId);

        return response()->json([
            'success' => true,
            'message' => 'Revision requested.',
        ]);
    }

    public function acknowledgeRevision(Request $request, string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $userId = $request->get('auth_user_id');

        $this->onboardingService->acknowledgeRevision($onboarding, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Revision acknowledged.',
        ]);
    }

    public function reopen(Request $request, string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $userId = $request->get('auth_user_id');

        $this->onboardingService->reopen($onboarding, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding reopened.',
        ]);
    }

    // --- New management endpoints ---

    public function reassignTrainer(ReassignTrainerRequest $request, string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $adminId = $request->get('auth_user_id');

        $this->onboardingService->reassignTrainer(
            $onboarding,
            $request->validated('trainer_id'),
            $adminId,
            $request->validated('notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Trainer reassigned successfully.',
        ]);
    }

    public function setDueDate(SetDueDateRequest $request, string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $userId = $request->get('auth_user_id');

        $this->onboardingService->setDueDate($onboarding, $request->validated('due_date'), $userId);

        return response()->json([
            'success' => true,
            'message' => 'Due date updated.',
        ]);
    }

    public function linkedAppointments(string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $linked = $this->onboardingService->listLinkedAppointments($onboarding);

        return response()->json([
            'success' => true,
            'data' => $linked,
        ]);
    }

    public function cycles(string $id): JsonResponse
    {
        $onboarding = OnboardingRequest::findOrFail($id);
        $cycles = $this->onboardingService->getCycles($onboarding);

        return response()->json([
            'success' => true,
            'data' => $cycles,
        ]);
    }

    public function trainerHistory(string $id): JsonResponse
    {
        $assignments = OnboardingTrainerAssignment::with([
            'trainer:id,first_name,last_name',
            'assignedBy:id,first_name,last_name',
        ])
            ->where('onboarding_id', $id)
            ->orderBy('assigned_at')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'trainer_id' => $a->trainer_id,
                'trainer_name' => $a->trainer ? "{$a->trainer->first_name} {$a->trainer->last_name}" : null,
                'assigned_by_id' => $a->assigned_by_id,
                'assigned_by_name' => $a->assignedBy ? "{$a->assignedBy->first_name} {$a->assignedBy->last_name}" : null,
                'assigned_at' => $a->assigned_at,
                'replaced_at' => $a->replaced_at,
                'is_current' => $a->is_current,
                'notes' => $a->notes,
            ]);

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }
}
