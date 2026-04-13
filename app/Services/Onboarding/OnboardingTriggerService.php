<?php

namespace App\Services\Onboarding;

use App\Models\Appointment;
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

    public function __construct(
        private NotificationService $notificationService,
        private TelegramGroupService $telegramGroupService,
    ) {}

    public function trigger(Appointment $appt): OnboardingRequest
    {
        $onboarding = DB::transaction(function () use ($appt) {
            $requestCode = $this->generateRequestCode();

            $onboarding = OnboardingRequest::create([
                'request_code' => $requestCode,
                'appointment_id' => $appt->id,
                'client_id' => $appt->client_id,
                'trainer_id' => $appt->trainer_id,
                'status' => 'pending',
                'progress_percentage' => 0,
            ]);

            // Seed default policies
            $now = now();
            $policies = array_map(fn ($name) => [
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

            // Mark appointment
            $appt->update([
                'is_onboarding_triggered' => true,
                'related_onboarding_id' => $onboarding->id,
            ]);

            return $onboarding;
        });

        // Notifications and cache invalidation run after the transaction commits
        // so that any cache-miss query sees the newly inserted rows.
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
}
