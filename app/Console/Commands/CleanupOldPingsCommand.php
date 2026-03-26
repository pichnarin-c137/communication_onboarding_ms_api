<?php

namespace App\Console\Commands;

use App\Models\TrainerLocationPing;
use Illuminate\Console\Command;

class CleanupOldPingsCommand extends Command
{
    protected $signature = 'tracking:cleanup-pings';

    protected $description = 'Delete location pings older than the configured retention period';

    public function handle(): int
    {
        $retentionDays = config('coms.tracking.ping_retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        $deleted = TrainerLocationPing::where('pinged_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} ping(s) older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
