<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\OnboardingLesson;
use App\Models\OnboardingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function trainerDashboard(Request $request): JsonResponse
    {
        $userId = $request->get('auth_user_id');
        $cacheKey = "dashboard:trainer:{$userId}";
        $ttl = config('coms.cache.dashboard_ttl', 180);

        $data = Cache::store('redis')->remember($cacheKey, $ttl, function () use ($userId) {
            $today = now()->toDateString();

            $totalAppointments = Appointment::where('trainer_id', $userId)->count();

            $appointmentsToday = Appointment::where('trainer_id', $userId)
                ->whereDate('scheduled_date', $today)
                ->count();

            $onboardingInProgress = OnboardingRequest::where('trainer_id', $userId)
                ->where('status', 'in_progress')
                ->count();

            $lessonsSentThisMonth = OnboardingLesson::where('sent_by_user_id', $userId)
                ->where('is_sent', true)
                ->whereMonth('sent_at', now()->month)
                ->whereYear('sent_at', now()->year)
                ->count();

            $upcomingAppointments = Appointment::with(['client:id,company_name'])
                ->where('trainer_id', $userId)
                ->whereIn('status', ['pending', 'leave_office'])
                ->whereDate('scheduled_date', '>=', $today)
                ->orderBy('scheduled_date')
                ->orderBy('scheduled_start_time')
                ->limit(5)
                ->get(['id', 'title', 'appointment_type', 'location_type', 'status',
                    'client_id', 'scheduled_date',
                    'scheduled_start_time', 'scheduled_end_time']);

            $onboardingProgress = OnboardingRequest::with(['client:id,company_name'])
                ->where('trainer_id', $userId)
                ->where('status', 'in_progress')
                ->orderByDesc('progress_percentage')
                ->limit(5)
                ->get(['id', 'request_code', 'client_id',
                    'status', 'progress_percentage', 'created_at']);

            return [
                'summary' => [
                    'total_appointments' => $totalAppointments,
                    'appointments_today' => $appointmentsToday,
                    'onboarding_in_progress' => $onboardingInProgress,
                    'lessons_sent_this_month' => $lessonsSentThisMonth,
                ],
                'upcoming_appointments' => $upcomingAppointments,
                'onboarding_progress' => $onboardingProgress,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function saleDashboard(Request $request): JsonResponse
    {
        $userId = $request->get('auth_user_id');
        $cacheKey = "dashboard:sale:{$userId}";
        $ttl = config('coms.cache.dashboard_ttl', 180);

        $data = Cache::store('redis')->remember($cacheKey, $ttl, function () use ($userId) {
            $today = now()->toDateString();

            $totalAppointments = Appointment::where('creator_id', $userId)->count();

            $demoAppointments = Appointment::where('creator_id', $userId)
                ->where('appointment_type', 'demo')
                ->count();

            $trainingAppointments = Appointment::where('creator_id', $userId)
                ->where('appointment_type', 'training')
                ->count();

            $onboardingCompleted = OnboardingRequest::whereHas(
                'appointment',
                fn ($q) => $q->where('creator_id', $userId)
            )->where('status', 'completed')->count();

            $upcomingAppointments = Appointment::with([
                'client:id,company_name',
                'trainer:id,first_name,last_name',
            ])
                ->where('creator_id', $userId)
                ->whereIn('status', ['pending', 'leave_office'])
                ->whereDate('scheduled_date', '>=', $today)
                ->orderBy('scheduled_date')
                ->orderBy('scheduled_start_time')
                ->limit(5)
                ->get(['id', 'title', 'appointment_type', 'location_type', 'status',
                    'client_id', 'trainer_id', 'scheduled_date',
                    'scheduled_start_time', 'scheduled_end_time']);

            $onboardingProgress = OnboardingRequest::with([
                'client:id,company_name',
                'trainer:id,first_name,last_name',
            ])
                ->whereHas('appointment', fn ($q) => $q->where('creator_id', $userId))
                ->where('status', 'in_progress')
                ->orderByDesc('progress_percentage')
                ->limit(5)
                ->get(['id', 'request_code', 'client_id', 'trainer_id',
                    'status', 'progress_percentage', 'created_at']);

            return [
                'summary' => [
                    'total_appointments' => $totalAppointments,
                    'demo_appointments' => $demoAppointments,
                    'training_appointments' => $trainingAppointments,
                    'onboarding_completed' => $onboardingCompleted,
                ],
                'upcoming_appointments' => $upcomingAppointments,
                'onboarding_progress' => $onboardingProgress,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
