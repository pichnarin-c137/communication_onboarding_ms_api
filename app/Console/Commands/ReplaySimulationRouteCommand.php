<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\SimulationRoute;
use App\Models\User;
use App\Services\Tracking\TrainerStatusService;
use App\Services\Tracking\TrainerTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ReplaySimulationRouteCommand extends Command
{
    protected $signature = 'simulation:replay
        {route_id : The simulation route UUID to replay}
        {--trainer-id= : The trainer user UUID to simulate pings for}
        {--customer-id= : The client UUID to target (triggers full lifecycle: en_route → pings → auto-arrive → in_session → completed → at_office)}
        {--speed=1 : Speed multiplier (1x = real-time, 10x = 10x faster)}
        {--pings-only : Only send GPS pings, skip status transitions}
        {--reset : Reset trainer status to at_office before starting (clears stale Redis state)}';

    protected $description = 'Simulate a full trainer field visit: leave office → drive to customer → auto-arrive → session → complete';

    public function handle(TrainerTrackingService $trackingService, TrainerStatusService $statusService): int
    {
        $routeId = $this->argument('route_id');
        $trainerId = $this->option('trainer-id');
        $customerId = $this->option('customer-id');
        $speedMultiplier = max(0.1, (float) $this->option('speed'));
        $pingsOnly = $this->option('pings-only');

        if (! $trainerId) {
            $this->error('--trainer-id is required.');
            return self::FAILURE;
        }

        $trainer = User::find($trainerId);
        if (! $trainer) {
            $this->error("Trainer not found: {$trainerId}");
            return self::FAILURE;
        }

        $route = SimulationRoute::find($routeId);
        if (! $route) {
            $this->error("Simulation route not found: {$routeId}");
            return self::FAILURE;
        }

        $waypoints = $route->waypoints;
        if (empty($waypoints)) {
            $this->error('Route has no waypoints.');
            return self::FAILURE;
        }

        $client = null;
        if ($customerId) {
            $client = Client::find($customerId);
            if (! $client) {
                $this->error("Client not found: {$customerId}");
                return self::FAILURE;
            }
        }

        $fullLifecycle = $client && ! $pingsOnly;

        // Reset stale trainer status if requested
        if ($this->option('reset')) {
            $keysToDelete = [
                "trainer:{$trainerId}:status",
                "trainer:{$trainerId}:geofence",
                "trainer:{$trainerId}:trail",
            ];
            foreach ($keysToDelete as $key) {
                Redis::del($key);
            }
            $this->warn('Reset: cleared trainer Redis state (status, geofence, trail).');
            $this->newLine();
        }

        $this->info("=== Simulation Replay ===");
        $this->info("Route:    {$route->name} ({$route->distance_meters}m, {$route->duration_seconds}s)");
        $this->info("Trainer:  {$trainer->first_name} {$trainer->last_name} ({$trainerId})");
        if ($client) {
            $this->info("Customer: {$client->company_name} ({$customerId})");
        }
        $this->info("Speed:    {$speedMultiplier}x | Waypoints: " . count($waypoints));
        $this->info("Mode:     " . ($fullLifecycle ? 'Full lifecycle (status + pings + auto-arrive)' : 'Pings only'));
        $this->newLine();

        //  Step 1: Transition to en_route 
        if ($fullLifecycle) {
            $this->info('[1/5] Changing status to en_route...');
            $currentStatus = $statusService->getCurrentStatus($trainerId);
            if ($currentStatus === 'en_route') {
                $this->warn("  ⏭ Already en_route — skipping transition.");
            } else {
                $firstWp = $waypoints[0];
                try {
                    $statusService->changeStatus($trainerId, 'en_route', [
                        'customer_id' => $customerId,
                        'latitude' => $firstWp['lat'],
                        'longitude' => $firstWp['lng'],
                    ]);
                    $this->info("  ✓ Status: en_route → geofence target set for {$client->company_name}");
                } catch (\Throwable $e) {
                    $this->error("  ✗ Failed: " . $e->getMessage());
                    $this->warn("  Hint: use --reset flag to clear stale status, or manually: php artisan tinker --execute=\"Illuminate\\Support\\Facades\\Redis::del('trainer:{$trainerId}:status')\"");
                    return self::FAILURE;
                }
            }
            $this->newLine();
        }

        //  Step 2: Send GPS pings along the route 
        $this->info(($fullLifecycle ? '[2/5]' : '[1/1]') . ' Sending GPS pings along route...');
        $bar = $this->output->createProgressBar(count($waypoints));
        $bar->start();

        $previousOffset = 0;
        $autoArrived = false;

        foreach ($waypoints as $i => $waypoint) {
            $currentOffset = $waypoint['timestamp_offset'] ?? 0;
            $delay = ($currentOffset - $previousOffset) / $speedMultiplier;

            if ($i > 0 && $delay > 0) {
                usleep((int) ($delay * 1_000_000));
            }

            try {
                $trackingService->processPing($trainerId, [
                    'latitude' => $waypoint['lat'],
                    'longitude' => $waypoint['lng'],
                    'accuracy' => $waypoint['accuracy'] ?? 10,
                    'speed' => $waypoint['speed'] ?? null,
                    'timestamp' => now()->toISOString(),
                ]);
            } catch (\Throwable $e) {
                // Don't show rejected pings in progress bar — just count them
            }

            // Check if geofence auto-arrival happened
            if ($fullLifecycle && ! $autoArrived) {
                $currentStatus = $statusService->getCurrentStatus($trainerId);
                if ($currentStatus === 'arrived') {
                    $autoArrived = true;
                    $bar->finish();
                    $this->newLine();
                    $this->info("  ✓ Auto-arrived at {$client->company_name} (geofence triggered at waypoint {$i}/" . count($waypoints) . ')');
                    break;
                }
            }

            $previousOffset = $currentOffset;
            $bar->advance();
        }

        if (! $autoArrived) {
            $bar->finish();
            $this->newLine();
            if ($fullLifecycle) {
                // If we finished all waypoints without auto-arrival, manually arrive
                $this->warn('  ⚠ Geofence did not trigger. Manually transitioning to arrived...');
                $lastWp = end($waypoints);
                try {
                    $statusService->changeStatus($trainerId, 'arrived', [
                        'customer_id' => $customerId,
                        'latitude' => $lastWp['lat'],
                        'longitude' => $lastWp['lng'],
                    ]);
                    $this->info("  ✓ Manually arrived.");
                } catch (\Throwable $e) {
                    $this->error("  ✗ Failed to arrive: " . $e->getMessage());
                    return self::FAILURE;
                }
            }
        }

        if (! $fullLifecycle) {
            $this->newLine();
            $this->info('Replay completed (pings only).');
            return self::SUCCESS;
        }

        //  Step 3: Start session 
        $this->newLine();
        $pauseSeconds = max(1, 5 / $speedMultiplier);
        $this->info("[3/5] Starting session in " . round($pauseSeconds) . "s...");
        usleep((int) ($pauseSeconds * 1_000_000));
        $lastWp = end($waypoints);
        try {
            $statusService->changeStatus($trainerId, 'in_session', [
                'customer_id' => $customerId,
                'latitude' => $lastWp['lat'],
                'longitude' => $lastWp['lng'],
            ]);
            $this->info("  ✓ Status: in_session");
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed: " . $e->getMessage());
            return self::FAILURE;
        }

        //  Step 4: Complete session 
        $sessionSeconds = max(2, 10 / $speedMultiplier);
        $this->info("[4/5] Session in progress for " . round($sessionSeconds) . "s...");
        usleep((int) ($sessionSeconds * 1_000_000));
        try {
            $statusService->changeStatus($trainerId, 'completed', [
                'customer_id' => $customerId,
                'latitude' => $lastWp['lat'],
                'longitude' => $lastWp['lng'],
            ]);
            $this->info("  ✓ Status: completed (trail flushed to DB, keys cleaned up)");
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed: " . $e->getMessage());
            return self::FAILURE;
        }

        //  Step 5: Return to office 
        $returnSeconds = max(1, 3 / $speedMultiplier);
        $this->info("[5/5] Returning to office in " . round($returnSeconds) . "s...");
        usleep((int) ($returnSeconds * 1_000_000));
        try {
            $statusService->changeStatus($trainerId, 'at_office', []);
            $this->info("  ✓ Status: at_office — simulation complete!");
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== Full lifecycle simulation completed ===');

        return self::SUCCESS;
    }
}
