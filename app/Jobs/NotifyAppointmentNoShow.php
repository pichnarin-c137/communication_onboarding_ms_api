<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyAppointmentNoShow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly Appointment $appointment,
    ) {
        $this->onQueue('high');
    }

    public function handle(NotificationService $notificationService): void
    {
        $adminIds = User::whereHas('role', fn ($q) => $q->where('role', 'admin'))
            ->get(['id'])
            ->pluck('id')
            ->all();

        if (empty($adminIds)) {
            return;
        }

        $scheduledAt = $this->appointment->scheduled_start_time;
        $minutesLate = now()->diffInMinutes(
            \Carbon\Carbon::parse(
                $this->appointment->scheduled_date->format('Y-m-d') . ' ' . $scheduledAt
            )
        );

        $notificationService->notify(
            $adminIds,
            'appointment_no_show',
            'Appointment No-Show Alert',
            "Trainer has not started appointment {$this->appointment->appointment_code} — scheduled at {$scheduledAt}, now {$minutesLate} minutes overdue.",
            ['type' => 'appointment', 'id' => $this->appointment->id],
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyAppointmentNoShow: failed after all retries.', [
            'appointment_id' => $this->appointment->id,
            'error'          => $e->getMessage(),
        ]);
    }
}
