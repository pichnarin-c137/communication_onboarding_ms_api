<?php

namespace App\Console\Commands;

use App\Services\Tracking\AnomalyDetectionService;
use App\Services\Tracking\TrainerTrackingService;
use Illuminate\Console\Command;

class FlushTrailsAndCheckAnomaliesCommand extends Command
{
    protected $signature = 'tracking:flush-and-check';

    protected $description = 'Flush Redis trail buffers to PostGIS and run anomaly detection checks';

    public function handle(
        TrainerTrackingService $trackingService,
        AnomalyDetectionService $anomalyService,
    ): int {
        $trainerIds = $trackingService->getEnRouteTrainerIds();

        $flushed = 0;
        foreach ($trainerIds as $trainerId) {
            $flushed += $trackingService->flushTrailToDatabase($trainerId);
        }

        $this->info("Flushed {$flushed} trail point(s) for " . count($trainerIds) . ' trainer(s).');

        $anomalies = $anomalyService->runAllChecks();
        $this->info("Detected {$anomalies} anomaly alert(s).");

        return self::SUCCESS;
    }
}
