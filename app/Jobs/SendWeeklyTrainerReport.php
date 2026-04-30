<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\OnboardingRequest;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWeeklyTrainerReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [60, 300];

    public function __construct(
        public readonly string $adminId,
    ) {
        $this->onQueue(config('coms.reports_queue', 'reports'));
    }

    public function handle(NotificationService $notificationService): void
    {
        $from = now()->subDays(7)->startOfDay();
        $to   = now()->endOfDay();

        $trainers = User::whereHas('role', fn ($q) => $q->where('role', 'trainer'))
            ->get(['id', 'first_name', 'last_name']);

        if ($trainers->isEmpty()) {
            return;
        }

        $trainerIds = $trainers->pluck('id')->all();

        $completedByTrainer = Appointment::whereBetween('actual_end_time', [$from, $to])
            ->where('status', 'done')
            ->whereIn('trainer_id', $trainerIds)
            ->selectRaw('trainer_id, count(*) as total')
            ->groupBy('trainer_id')
            ->pluck('total', 'trainer_id');

        $activeOnboardingsByTrainer = OnboardingRequest::whereIn('trainer_id', $trainerIds)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->selectRaw('trainer_id, count(*) as total')
            ->groupBy('trainer_id')
            ->pluck('total', 'trainer_id');

        $lines = $trainers->map(function (User $trainer) use ($completedByTrainer, $activeOnboardingsByTrainer) {
            $name       = "{$trainer->first_name} {$trainer->last_name}";
            $completed  = $completedByTrainer->get($trainer->id, 0);
            $onboarding = $activeOnboardingsByTrainer->get($trainer->id, 0);

            return "{$name}: {$completed} appointment(s) done, {$onboarding} onboarding(s) active";
        })->implode(' | ');

        $notificationService->notify(
            [$this->adminId],
            'weekly_trainer_report',
            'Weekly Trainer Report',
            "Last 7 days — {$lines}.",
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendWeeklyTrainerReport: failed after all retries.', [
            'admin_id' => $this->adminId,
            'error'    => $e->getMessage(),
        ]);
    }
}
