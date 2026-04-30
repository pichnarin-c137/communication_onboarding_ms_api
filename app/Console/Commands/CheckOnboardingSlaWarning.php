<?php

namespace App\Console\Commands;

use App\Services\Onboarding\OnboardingSlaService;
use Illuminate\Console\Command;

class CheckOnboardingSlaWarning extends Command
{
    protected $signature = 'onboarding:check-sla-warning';

    protected $description = 'Warn trainers and sales when onboarding due dates are approaching within 3 days.';

    public function __construct(
        private OnboardingSlaService $slaService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->slaService->checkUpcomingDeadlines();

        if ($count > 0) {
            $this->warn("{$count} onboarding(s) approaching their SLA deadline.");
        } else {
            $this->info('No upcoming SLA deadlines found.');
        }

        return self::SUCCESS;
    }
}
