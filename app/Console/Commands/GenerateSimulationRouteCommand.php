<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\SimulationRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GenerateSimulationRouteCommand extends Command
{
    protected $signature = 'simulation:generate-route
        {--from=office : Starting location key or "lat,lng" coordinates}
        {--to= : Destination location key or "lat,lng" coordinates}
        {--to-customer= : Client UUID — route ends at this client\'s headquarter location}
        {--name= : Route name (auto-generated if not provided)}';

    protected $description = 'Generate a simulation route using OSRM. Use --to-customer for realistic demo routes ending at a real client.';

    private const LOCATIONS = [
        'office' => [11.5564, 104.9160],
        'acleda' => [11.5684, 104.9282],
        'wing' => [11.5555, 104.9300],
        'smart' => [11.5750, 104.8950],
    ];

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $toCustomerId = $this->option('to-customer');

        // Resolve FROM coordinates
        $fromCoords = $this->resolveCoords($from);
        if (! $fromCoords) {
            $this->error("Unknown --from location: {$from}. Use a preset (" . implode(', ', array_keys(self::LOCATIONS)) . ') or lat,lng format.');
            return self::FAILURE;
        }

        // Resolve TO coordinates — either from --to-customer or --to
        $toLabel = null;
        if ($toCustomerId) {
            $client = Client::find($toCustomerId);
            if (! $client) {
                $this->error("Client not found: {$toCustomerId}");
                return self::FAILURE;
            }
            if (! $client->headquarter_latitude || ! $client->headquarter_longitude) {
                $this->error("Client '{$client->company_name}' has no GPS coordinates.");
                return self::FAILURE;
            }
            $toCoords = [(float) $client->headquarter_latitude, (float) $client->headquarter_longitude];
            $toLabel = $client->company_name;
            $this->info("Target customer: {$client->company_name} ({$client->code})");
            $this->info("  Location: {$toCoords[0]}, {$toCoords[1]}");
            $this->info("  Geofence radius: {$client->geofence_radius}m");
        } elseif ($to) {
            $toCoords = $this->resolveCoords($to);
            if (! $toCoords) {
                $this->error("Unknown --to location: {$to}. Use a preset or lat,lng format.");
                return self::FAILURE;
            }
            $toLabel = $to;
        } else {
            $this->error('Provide either --to=<location> or --to-customer=<client-uuid>.');
            $this->newLine();
            $this->info('Available presets: ' . implode(', ', array_keys(self::LOCATIONS)));
            $this->info('Or use --to-customer with a client UUID. List clients:');
            $this->info("  php artisan tinker --execute=\"App\\Models\\Client::select('id','company_name','code','headquarter_latitude','headquarter_longitude')->get()\"");
            return self::FAILURE;
        }

        [$fromLat, $fromLng] = $fromCoords;
        [$toLat, $toLng] = $toCoords;

        $name = $this->option('name') ?: ucfirst($from) . ' to ' . ($toLabel ?: 'destination');

        $this->info("Fetching route from OSRM: ({$fromLat},{$fromLng}) → ({$toLat},{$toLng})...");

        $baseUrl = config('coms.tracking.osrm_base_url', 'https://router.project-osrm.org');

        $response = Http::timeout(30)->get(
            "{$baseUrl}/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}",
            [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'true',
            ]
        );

        if (! $response->successful()) {
            $this->error('OSRM API request failed: ' . $response->status());
            return self::FAILURE;
        }

        $body = $response->json();
        if (($body['code'] ?? '') !== 'Ok' || empty($body['routes'])) {
            $this->error('OSRM returned no routes.');
            return self::FAILURE;
        }

        $route = $body['routes'][0];
        $geometry = $route['geometry']['coordinates'] ?? [];
        $duration = (int) $route['duration'];
        $distance = (int) $route['distance'];

        if (empty($geometry)) {
            $this->error('Route geometry is empty.');
            return self::FAILURE;
        }

        // Convert geometry coordinates to waypoints with GPS jitter
        $waypoints = [];
        $totalPoints = count($geometry);
        $timePerPoint = $totalPoints > 1 ? $duration / ($totalPoints - 1) : 0;

        foreach ($geometry as $i => [$lng, $lat]) {
            // Add GPS jitter: 3-10m random offset (but NOT on last 3 points — keep them accurate for geofence)
            $isNearEnd = $i >= $totalPoints - 3;
            $jitterMeters = $isNearEnd ? rand(1, 3) : rand(3, 10);
            $jitterAngle = deg2rad(rand(0, 360));
            $jitterLat = ($jitterMeters * cos($jitterAngle)) / 111320;
            $jitterLng = ($jitterMeters * sin($jitterAngle)) / (111320 * cos(deg2rad($lat)));

            $waypoints[] = [
                'lat' => round($lat + $jitterLat, 7),
                'lng' => round($lng + $jitterLng, 7),
                'timestamp_offset' => round($i * $timePerPoint, 1),
                'accuracy' => $isNearEnd ? rand(5, 15) : rand(5, 30),
                'speed' => $totalPoints > 1 ? round(($distance / $duration) * 3.6, 1) : 0,
            ];
        }

        $simulationRoute = SimulationRoute::create([
            'name' => $name,
            'from_label' => $from,
            'to_label' => $toLabel ?: ($to ?: 'custom'),
            'waypoints' => $waypoints,
            'duration_seconds' => $duration,
            'distance_meters' => $distance,
        ]);

        $this->newLine();
        $this->info("Route '{$name}' created successfully.");
        $this->info("  ID:        {$simulationRoute->id}");
        $this->info("  Waypoints: " . count($waypoints));
        $this->info("  Duration:  {$duration}s (" . round($duration / 60, 1) . ' min)');
        $this->info("  Distance:  {$distance}m (" . round($distance / 1000, 1) . ' km)');

        if ($toCustomerId) {
            $this->newLine();
            $this->info('To run the full demo:');
            $this->info("  php artisan simulation:replay {$simulationRoute->id} --trainer-id=<TRAINER_UUID> --customer-id={$toCustomerId} --speed=50");
        }

        return self::SUCCESS;
    }

    private function resolveCoords(string $input): ?array
    {
        // Check presets
        if (isset(self::LOCATIONS[$input])) {
            return self::LOCATIONS[$input];
        }

        // Check lat,lng format
        if (str_contains($input, ',')) {
            $parts = explode(',', $input);
            if (count($parts) === 2 && is_numeric(trim($parts[0])) && is_numeric(trim($parts[1]))) {
                return [(float) trim($parts[0]), (float) trim($parts[1])];
            }
        }

        return null;
    }
}


// Example usage:
// php artisan simulation:generate-route --from=office --to-customer=CLIENT_UUID

// Replaying a simulation route example:
// php artisan simulation:replay SIMULATION_ROUTE_ID --trainer-id=TRAINER_UUID --customer-id=CLIENT_UUID --speed=50

