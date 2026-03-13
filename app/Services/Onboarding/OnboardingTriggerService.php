<?php

namespace App\Services\Onboarding;

use App\Models\Appointment;
use App\Models\OnboardingCompanyInfo;
use App\Models\OnboardingPolicy;
use App\Models\OnboardingRequest;
use App\Models\OnboardingSystemAnalysis;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OnboardingTriggerService
{
    private const DEFAULT_POLICIES = [
        'Shift & Attendance',
        'Leave',
        'Payroll',
    ];

    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function trigger(Appointment $appt): OnboardingRequest
    {
        return DB::transaction(function () use ($appt) {
            $requestCode = $this->generateRequestCode();

            $onboarding = OnboardingRequest::create([
                'request_code'  => $requestCode,
                'appointment_id' => $appt->id,
                'client_id'     => $appt->client_id,
                'trainer_id'    => $appt->trainer_id,
                'status'        => 'in_progress',
                'progress_percentage' => 0,
            ]);

            // Seed default policies
            $now = now();
            $policies = array_map(fn ($name) => [
                'id'          => (string) \Illuminate\Support\Str::uuid(),
                'onboarding_id' => $onboarding->id,
                'policy_name' => $name,
                'is_default'  => true,
                'is_checked'  => false,
                'checked_at'  => null,
                'checked_by_user_id' => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ], self::DEFAULT_POLICIES);

            OnboardingPolicy::insert($policies);

            // Seed empty company info
            OnboardingCompanyInfo::create([
                'onboarding_id' => $onboarding->id,
                'content'       => null,
                'is_completed'  => false,
            ]);

            // Seed empty system analysis
            OnboardingSystemAnalysis::create([
                'onboarding_id'         => $onboarding->id,
                'import_employee_count' => 0,
                'connected_app_count'   => 0,
                'profile_mobile_count'  => 0,
            ]);

            // Mark appointment
            $appt->update([
                'is_onboarding_triggered' => true,
                'related_onboarding_id'   => $onboarding->id,
            ]);

            // Notify creator (sale)
            try {
                $this->notificationService->notify(
                    [$appt->creator_id],
                    'onboarding_created',
                    'Onboarding Request Created',
                    "Onboarding request {$requestCode} has been created automatically after completing the training appointment.",
                    ['type' => 'onboarding_request', 'id' => $onboarding->id]
                );

                // If trainer is different from creator, also notify trainer
                if ($appt->trainer_id && $appt->trainer_id !== $appt->creator_id) {
                    $this->notificationService->notify(
                        [$appt->trainer_id],
                        'onboarding_created',
                        'Onboarding Request Assigned',
                        "You have been assigned to onboarding request {$requestCode}.",
                        ['type' => 'onboarding_request', 'id' => $onboarding->id]
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('OnboardingTriggerService notification failed', [
                    'onboarding_id' => $onboarding->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Invalidate onboarding list caches for trainer and sale (creator)
            foreach (array_unique(array_filter([$appt->trainer_id, $appt->creator_id])) as $userId) {
                Cache::store('redis')->forget("onboarding:list:{$userId}");
            }

            return $onboarding;
        });
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
