<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\CompleteAppointmentRequest;
use App\Http\Requests\CreateAppointmentRequest;
use App\Http\Requests\LeaveOfficeRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\StartAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Appointment\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(
        private AppointmentService $appointmentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user    = User::findOrFail($request->get('auth_user_id'));
        $filters = $request->only(['status', 'appointment_type', 'scheduled_date', 'client_id']);
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $page    = max(1, (int) $request->input('page', 1));

        $result = $this->appointmentService->list($user, $filters, $perPage, $page);

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => $result['meta'],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $appointment = $this->appointmentService->get($id);

        return response()->json([
            'success' => true,
            'data'    => $appointment,
        ]);
    }

    public function store(CreateAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->appointmentService->create(
            $request->validated(),
            $request->get('auth_user_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Appointment created successfully.',
            'data'    => $appointment,
        ], 201);
    }

    public function update(UpdateAppointmentRequest $request, string $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $updated = $this->appointmentService->update($appointment, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Appointment updated successfully.',
            'data'    => $updated,
        ]);
    }

    public function leaveOffice(LeaveOfficeRequest $request, string $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        $this->appointmentService->leaveOffice(
            $appointment,
            (float) $request->input('latitude'),
            (float) $request->input('longitude')
        );

        return response()->json([
            'success' => true,
            'message' => 'Trainer has left the office.',
            'data'    => ['status' => $appointment->fresh()->status],
        ]);
    }

    public function start(StartAppointmentRequest $request, string $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        $this->appointmentService->startAppointment(
            $appointment,
            $request->input('start_proof_media_id'),
            (float) $request->input('start_latitude'),
            (float) $request->input('start_longitude')
        );

        return response()->json([
            'success' => true,
            'message' => 'Appointment started.',
            'data'    => ['status' => $appointment->fresh()->status],
        ]);
    }

    public function complete(CompleteAppointmentRequest $request, string $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        $this->appointmentService->completeAppointment(
            $appointment,
            $request->input('end_proof_media_id'),
            (float) $request->input('end_latitude'),
            (float) $request->input('end_longitude'),
            (int) $request->input('student_count'),
            $request->input('completion_notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Appointment completed.',
            'data'    => ['status' => $appointment->fresh()->status],
        ]);
    }

    public function cancel(CancelAppointmentRequest $request, string $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        $this->appointmentService->cancel(
            $appointment,
            $request->input('cancellation_reason'),
            $request->get('auth_user_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Appointment cancelled.',
        ]);
    }

    public function reschedule(RescheduleAppointmentRequest $request, string $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        $newAppointment = $this->appointmentService->reschedule($appointment, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Appointment rescheduled.',
            'data'    => [
                'new_appointment_id' => $newAppointment->id,
                'status'             => $newAppointment->status,
            ],
        ]);
    }
}
