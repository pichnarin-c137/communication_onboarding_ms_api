<?php

namespace App\Console\Commands;

use App\Services\Tracking\EtaService;
use Illuminate\Console\Command;

class RecalculateEtaCommand extends Command
{
    protected $signature = 'tracking:recalculate-eta';

    protected $description = 'Recalculate ETA for all en-route trainers via OSRM';

    public function handle(EtaService $etaService): int
    {
        $count = $etaService->recalculateForAllEnRoute();
        $this->info("Recalculated ETA for {$count} trainer(s).");

        return self::SUCCESS;
    }
}
