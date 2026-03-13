<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddStudentRequest;
use App\Http\Requests\MarkAttendanceRequest;
use App\Models\Appointment;
use App\Models\AppointmentStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AppointmentStudentController extends Controller
{
    public function index(string $appointmentId): JsonResponse
    {
        $appointment = Appointment::findOrFail($appointmentId);
        $students = $appointment->students()->get();

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    public function store(AddStudentRequest $request, string $appointmentId): JsonResponse
    {
        $appointment = Appointment::findOrFail($appointmentId);

        $now = now();
        $rows = array_map(fn ($s) => array_merge($s, [
            'id'             => (string) Str::uuid(),
            'appointment_id' => $appointment->id,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]), $request->input('students'));

        AppointmentStudent::insert($rows);

        return response()->json([
            'success' => true,
            'message' => 'Students added successfully.',
            'data'    => ['count' => count($rows)],
        ], 201);
    }

    public function markAttendance(MarkAttendanceRequest $request, string $appointmentId, string $studentId): JsonResponse
    {
        $student = AppointmentStudent::where('appointment_id', $appointmentId)
            ->where('id', $studentId)
            ->firstOrFail();

        $student->update(['attendance_status' => $request->input('attendance_status')]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance marked.',
            'data'    => ['attendance_status' => $student->fresh()->attendance_status],
        ]);
    }
}
