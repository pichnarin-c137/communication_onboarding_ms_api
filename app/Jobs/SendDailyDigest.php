<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\OnboardingRequest;
use App\Models\UserSetting;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDailyDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [60, 300];

    public function __construct(
        public readonly string $userId,
    ) {
        $this->onQueue(config('coms.reports_queue', 'reports'));
    }

    public function handle(NotificationService $notificationService): void
    {
        $tz    = UserSetting::where('user_id', $this->userId)->value('timezone') ?? config('coms.user_settings.defaults.timezone', 'Asia/Phnom_Penh');
        $today = now($tz)->toDateString();

        $appointmentCounts = Appointment::whereDate('scheduled_date', $today)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalToday  = $appointmentCounts->sum();
        $doneToday   = $appointmentCounts->get('done', 0);
        $pendingToday = $appointmentCounts->get('pending', 0);

        $overdueOnboardings = OnboardingRequest::whereNotNull('due_date')
            ->whereDate('due_date', '<', now($tz)->toDateString())
            ->whereNull('sla_breached_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $activeOnboardings = OnboardingRequest::whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $message = "Today: {$totalToday} appointment(s) — {$doneToday} done, {$pendingToday} pending. "
            . "Onboardings: {$activeOnboardings} active, {$overdueOnboardings} overdue.";

        $notificationService->notify(
            [$this->userId],
            'daily_digest',
            'Daily Digest',
            $message,
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendDailyDigest: failed after all retries.', [
            'user_id' => $this->userId,
            'error'   => $e->getMessage(),
        ]);
    }
}
