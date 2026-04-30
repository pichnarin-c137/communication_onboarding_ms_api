<?php

namespace App\Console\Commands;

use App\Services\Onboarding\OnboardingSlaService;
use Illuminate\Console\Command;

//daily at 00:00 Midnight — marks actual breaches
class CheckOnboardingSla extends Command
{
    protected $signature = 'onboarding:check-sla';

    protected $description = 'Check for onboarding SLA breaches and notify relevant users.';

    public function __construct(
        private OnboardingSlaService $slaService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->slaService->checkAllBreaches();

        if ($count > 0) {
            $this->warn("{$count} onboarding(s) have breached their SLA deadline.");
        } else {
            $this->info('No SLA breaches found.');
        }

        return Command::SUCCESS;
    }
}
