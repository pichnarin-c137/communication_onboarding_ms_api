<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $type,
    ) {
        $this->onQueue('high');
    }

    public function handle(NotificationService $notificationService): void
    {
        if (! $this->appointment->trainer_id) {
            return;
        }

        $date = $this->appointment->scheduled_date->format('Y-m-d');
        $time = $this->appointment->scheduled_start_time;
        $label = $this->type === '24h' ? '24 hours' : '1 hour';

        $notificationService->notify(
            [$this->appointment->trainer_id],
            'appointment_reminder',
            'Appointment Reminder',
            "You have an appointment in {$label}: {$this->appointment->title} on {$date} at {$time}.",
            ['type' => 'appointment', 'id' => $this->appointment->id],
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendAppointmentReminder: failed after all retries.', [
            'appointment_id' => $this->appointment->id,
            'type'           => $this->type,
            'error'          => $e->getMessage(),
        ]);
    }
}
