<?php

namespace App\Console\Commands;

use App\Jobs\SendWeeklyTrainerReport;
use App\Models\User;
use Illuminate\Console\Command;

class SendWeeklyTrainerReportCommand extends Command
{
    protected $signature = 'reports:weekly-trainer-report';

    protected $description = 'Dispatch weekly trainer performance report notifications to all admins.';

    public function handle(): int
    {
        $admins = User::whereHas('role', fn ($q) => $q->where('role', 'admin'))
            ->get(['id']);

        foreach ($admins as $admin) {
            SendWeeklyTrainerReport::dispatch($admin->id);
        }

        $this->info("Dispatched weekly trainer report for {$admins->count()} admin(s).");

        return self::SUCCESS;
    }
}
