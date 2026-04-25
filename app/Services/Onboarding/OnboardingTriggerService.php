<?php

namespace App\Services\Onboarding;

use App\Models\Appointment;
use App\Models\OnboardingAppointment;
use App\Models\OnboardingCompanyInfo;
use App\Models\OnboardingPolicy;
use App\Models\OnboardingRequest;
use App\Models\OnboardingSystemAnalysis;
use App\Services\Notification\NotificationService;
use App\Services\Telegram\TelegramGroupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingTriggerService
{
    private const DEFAULT_POLICIES = [
        'Shift & Attendance',
        'Leave',
        'Payroll',
    ];

    private const INCOMING_ELIGIBLE_STATUSES = [
        'pending',
        'leave_office',
        'in_progress',
    ];

    public function __construct(
        private NotificationService $notificationService,
        private TelegramGroupService $telegramGroupService,
    ) {}

    /**
     * Entry point: decide which path to take for a completed training appointment.
     */
    public function handleAppointmentCompleted(Appointment $appt): void
    {
        $activeOnboarding = $this->findActiveOnboarding($appt->client_id);
        $completedOnboarding = $this->findLatestCompletedOnboarding($appt->client_id);

        if (! $appt->is_continued_session && $activeOnboarding === null && $completedOnboarding === null) {
            // Path A: first onboarding for this client
            $this->trigger($appt);
        } elseif ($appt->is_continued_session && $activeOnboarding !== null) {
            // Path B: continued session during active onboarding
            $this->linkSupplementalSession($appt, $activeOnboarding);
        } elseif (! $appt->is_continued_session && $activeOnboarding !== null) {
            // Path E: new appointment requested while onboarding is still active — link as supplemental
            $this->linkSupplementalSession($appt, $activeOnboarding);
        } elseif (! $appt->is_continued_session && $completedOnboarding !== null) {
            // Path C: re-training after completion — new cycle
            $this->triggerNewCycle($appt, $completedOnboarding);
        } elseif ($appt->is_continued_session && $activeOnboarding === null) {
            // Path D: orphaned continued session — log warning
            Log::warning('OnboardingTriggerService: continued session with no active onboarding', [
                'appointment_id' => $appt->id,
                'client_id' => $appt->client_id,
            ]);
        }
    }

    /**
     * Pre-link any in-flight training appointment (pending/leave_office/in_progress) to the active onboarding as "incoming".
     * Upgraded to "supplemental" automatically when the appointment completes.
     */
    public function handleAppointmentInProgress(Appointment $appt): void
    {
        if ($appt->appointment_type !== 'training') {
            return;
        }

        if (! in_array($appt->status, self::INCOMING_ELIGIBLE_STATUSES, true)) {
            return;
        }

        $activeOnboarding = $this->findActiveOnboarding($appt->client_id);

        if ($activeOnboarding === null) {
            return;
        }

        $existing = OnboardingAppointment::where('appointment_id', $appt->id)->first();

        if ($existing && $existing->session_type !== 'incoming') {
            return;
        }

        if ($existing) {
            $existing->update([
                'onboarding_id' => $activeOnboarding->id,
                'session_type' => 'incoming',
                'linked_at' => now(),
            ]);

            return;
        }

        OnboardingAppointment::create([
            'onboarding_id' => $activeOnboarding->id,
            'appointment_id' => $appt->id,
            'session_type' => 'incoming',
            'linked_at' => now(),
        ]);
    }

    public function trigger(Appointment $appt): OnboardingRequest
    {
        $onboarding = DB::transaction(function () use ($appt) {
            $requestCode = $this->generateRequestCode();
            $dueDate = $appt->scheduled_date->addDays(config('coms.onboarding_due_days', 30));

            $onboarding = OnboardingRequest::create([
                'request_code' => $requestCode,
                'appointment_id' => $appt->id,
                'client_id' => $appt->client_id,
                'trainer_id' => $appt->trainer_id,
                'status' => 'pending',
                'progress_percentage' => 0,
                'due_date' => $dueDate,
            ]);

            // Seed default policies
            $now = now();
            $policies = array_map(fn($name) => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'onboarding_id' => $onboarding->id,
                'policy_name' => $name,
                'is_default' => true,
                'is_checked' => false,
                'checked_at' => null,
                'checked_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], self::DEFAULT_POLICIES);

            OnboardingPolicy::insert($policies);

            // Seed empty company info
            OnboardingCompanyInfo::create([
                'onboarding_id' => $onboarding->id,
                'content' => null,
                'is_completed' => false,
            ]);

            // Seed empty system analysis
            OnboardingSystemAnalysis::create([
                'onboarding_id' => $onboarding->id,
                'import_employee_count' => 0,
                'connected_app_count' => 0,
                'profile_mobile_count' => 0,
            ]);

            // Link appointment via pivot
            $this->upsertAppointmentLink($appt, $onboarding, 'primary');

            // Mark appointment
            $appt->update([
                'is_onboarding_triggered' => true,
                'related_onboarding_id' => $onboarding->id,
            ]);

            return $onboarding;
        });

        try {
            $this->notificationService->notify(
                [$appt->creator_id],
                'onboarding_created',
                'Onboarding Request Created',
                "Onboarding request {$onboarding->request_code} has been created automatically after completing the training appointment.",
                ['type' => 'onboarding_request', 'id' => $onboarding->id]
            );

            if ($appt->trainer_id && $appt->trainer_id !== $appt->creator_id) {
                $this->notificationService->notify(
                    [$appt->trainer_id],
                    'onboarding_created',
                    'Onboarding Request Assigned',
                    "You have been assigned to onboarding request {$onboarding->request_code}.",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingTriggerService notification failed', [
                'onboarding_id' => $onboarding->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->telegramGroupService->notifyClient($appt->client_id, 'onboarding_started', [
                'client_name' => $appt->client?->company_name ?? 'Client',
            ]);
        } catch (\Throwable $e) {
            Log::error('OnboardingTriggerService Telegram notification failed', [
                'onboarding_id' => $onboarding->id,
                'error'         => $e->getMessage(),
            ]);
        }

        foreach (array_unique(array_filter([$appt->trainer_id, $appt->creator_id])) as $userId) {
            Cache::store('redis')->forget("onboarding:list:{$userId}");
        }

        return $onboarding;
    }

    public function linkSupplementalSession(Appointment $appt, OnboardingRequest $onboarding): void
    {
        DB::transaction(function () use ($appt, $onboarding) {
            $this->upsertAppointmentLink($appt, $onboarding, 'supplemental');

            $appt->update([
                'is_onboarding_triggered' => true,
                'related_onboarding_id' => $onboarding->id,
            ]);
        });

        try {
            if ($appt->trainer_id) {
                $this->notificationService->notify(
                    [$appt->trainer_id],
                    'onboarding_supplemental_linked',
                    'Supplemental Session Linked',
                    "A supplemental training session has been linked to onboarding {$onboarding->request_code}.",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingTriggerService linkSupplementalSession notification failed', [
                'onboarding_id' => $onboarding->id,
                'appointment_id' => $appt->id,
                'error' => $e->getMessage(),
            ]);
        }

        Cache::store('redis')->forget("onboarding:show:{$onboarding->id}");
    }

    public function triggerNewCycle(Appointment $appt, OnboardingRequest $previous): OnboardingRequest
    {
        $onboarding = DB::transaction(function () use ($appt, $previous) {
            $requestCode = $this->generateRequestCode();
            $dueDate = $appt->scheduled_date->addDays(config('coms.onboarding_due_days', 30));
            $cycleNumber = $previous->cycle_number + 1;

            $onboarding = OnboardingRequest::create([
                'request_code' => $requestCode,
                'appointment_id' => $appt->id,
                'client_id' => $appt->client_id,
                'trainer_id' => $appt->trainer_id,
                'status' => 'pending',
                'progress_percentage' => 0,
                'due_date' => $dueDate,
                'parent_onboarding_id' => $previous->id,
                'cycle_number' => $cycleNumber,
            ]);

            // Seed default policies
            $now = now();
            $policies = array_map(fn($name) => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'onboarding_id' => $onboarding->id,
                'policy_name' => $name,
                'is_default' => true,
                'is_checked' => false,
                'checked_at' => null,
                'checked_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], self::DEFAULT_POLICIES);

            OnboardingPolicy::insert($policies);

            // Seed empty company info
            OnboardingCompanyInfo::create([
                'onboarding_id' => $onboarding->id,
                'content' => null,
                'is_completed' => false,
            ]);

            // Seed empty system analysis
            OnboardingSystemAnalysis::create([
                'onboarding_id' => $onboarding->id,
                'import_employee_count' => 0,
                'connected_app_count' => 0,
                'profile_mobile_count' => 0,
            ]);

            // Link appointment via pivot
            $this->upsertAppointmentLink($appt, $onboarding, 'retraining');

            // Mark appointment
            $appt->update([
                'is_onboarding_triggered' => true,
                'related_onboarding_id' => $onboarding->id,
            ]);

            return $onboarding;
        });

        try {
            $recipients = array_unique(array_filter([$appt->creator_id, $appt->trainer_id]));
            if (! empty($recipients)) {
                $this->notificationService->notify(
                    $recipients,
                    'onboarding_cycle_created',
                    "Onboarding Cycle {$onboarding->cycle_number} Created",
                    "Onboarding cycle {$onboarding->cycle_number} has been created for request {$onboarding->request_code}.",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );
            }
        } catch (\Throwable $e) {
            Log::error('OnboardingTriggerService triggerNewCycle notification failed', [
                'onboarding_id' => $onboarding->id,
                'error' => $e->getMessage(),
            ]);
        }

        foreach (array_unique(array_filter([$appt->trainer_id, $appt->creator_id])) as $userId) {
            Cache::store('redis')->forget("onboarding:list:{$userId}");
        }

        return $onboarding;
    }

    private function findActiveOnboarding(string $clientId): ?OnboardingRequest
    {
        return OnboardingRequest::where('client_id', $clientId)
            ->whereIn('status', ['pending', 'in_progress', 'on_hold', 'revision_requested'])
            ->latest()
            ->first();
    }

    private function findLatestCompletedOnboarding(string $clientId): ?OnboardingRequest
    {
        return OnboardingRequest::where('client_id', $clientId)
            ->where('status', 'completed')
            ->latest()
            ->first();
    }

    private function generateRequestCode(): string
    {
        $year = now()->year;
        $last = OnboardingRequest::withTrashed()
            ->where('request_code', 'like', "APT-{$year}-%")
            ->orderByDesc('request_code')
            ->value('request_code');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('APT-%d-%04d', $year, $seq);
    }

    private function upsertAppointmentLink(Appointment $appt, OnboardingRequest $onboarding, string $sessionType): void
    {
        $existing = OnboardingAppointment::where('appointment_id', $appt->id)->first();

        if ($existing) {
            $existing->update([
                'onboarding_id' => $onboarding->id,
                'session_type' => $sessionType,
                'linked_at' => now(),
            ]);

            return;
        }

        OnboardingAppointment::create([
            'onboarding_id' => $onboarding->id,
            'appointment_id' => $appt->id,
            'session_type' => $sessionType,
            'linked_at' => now(),
        ]);
    }
}
