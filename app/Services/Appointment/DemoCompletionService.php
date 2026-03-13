<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Services\Notification\NotificationService;

class DemoCompletionService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(Appointment $appt): void
    {
        try {
            $this->notificationService->notify(
                [$appt->creator_id],
                'demo_completed',
                'Demo Appointment Completed',
                "Demo appointment '{$appt->title}' has been completed successfully.",
                ['type' => 'appointment', 'id' => $appt->id]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DemoCompletionService notification failed', [
                'appointment_id' => $appt->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
