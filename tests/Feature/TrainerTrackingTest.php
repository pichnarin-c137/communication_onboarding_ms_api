<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\Tracking\TrainerStatusService;
use App\Services\Tracking\TrainerTrackingService;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class TrainerTrackingTest extends TestCase
{
    private User $trainer;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trainer = $this->createUser(['role' => 'trainer']);
        $this->headers = $this->authHeadersFor($this->trainer);

        // Clear Redis keys used by tracking
        Redis::del('trainer:locations');
        Redis::del('geofence:targets');
        Redis::del('customer:locations');
        Redis::del(TrainerTrackingService::trailKey($this->trainer->id));
        Redis::del(TrainerTrackingService::statusKey($this->trainer->id));
        Redis::del(TrainerTrackingService::etaKey($this->trainer->id));
    }

    //  Location Ping Validation 

    public function test_it_accepts_valid_ping(): void
    {
        $response = $this->postJson('/api/v1/trainer/location-ping', [
            'latitude' => 11.5564,
            'longitude' => 104.9160,
            'accuracy' => 15.0,
            'speed' => 30.0,
            'timestamp' => now()->toISOString(),
        ], $this->headers);

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Ping received.']);
    }

    public function test_it_rejects_ping_with_accuracy_over_100m(): void
    {
        $response = $this->postJson('/api/v1/trainer/location-ping', [
            'latitude' => 11.5564,
            'longitude' => 104.9160,
            'accuracy' => 150.0,
            'speed' => 10.0,
            'timestamp' => now()->toISOString(),
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_it_rejects_ping_with_timestamp_over_60s_old(): void
    {
        $response = $this->postJson('/api/v1/trainer/location-ping', [
            'latitude' => 11.5564,
            'longitude' => 104.9160,
            'accuracy' => 15.0,
            'speed' => 10.0,
            'timestamp' => now()->subSeconds(120)->toISOString(),
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_it_rejects_ping_with_zero_accuracy_as_spoofing(): void
    {
        $response = $this->postJson('/api/v1/trainer/location-ping', [
            'latitude' => 11.5564,
            'longitude' => 104.9160,
            'accuracy' => 0,
            'speed' => 10.0,
            'timestamp' => now()->toISOString(),
        ], $this->headers);

        $response->assertStatus(422);

        $this->assertDatabaseHas('anomaly_alerts', [
            'trainer_id' => $this->trainer->id,
            'type' => 'gps_spoofing',
        ]);
    }

    public function test_it_rejects_ping_with_excessive_speed(): void
    {
        $response = $this->postJson('/api/v1/trainer/location-ping', [
            'latitude' => 11.5564,
            'longitude' => 104.9160,
            'accuracy' => 10.0,
            'speed' => 250.0,
            'timestamp' => now()->toISOString(),
        ], $this->headers);

        $response->assertStatus(422);

        $this->assertDatabaseHas('anomaly_alerts', [
            'trainer_id' => $this->trainer->id,
            'type' => 'gps_spoofing',
        ]);
    }

    public function test_it_stores_ping_in_redis(): void
    {
        $this->postJson('/api/v1/trainer/location-ping', [
            'latitude' => 11.5564,
            'longitude' => 104.9160,
            'accuracy' => 15.0,
            'timestamp' => now()->toISOString(),
        ], $this->headers);

        // Verify Redis GEO entry
        $pos = Redis::geopos('trainer:locations', $this->trainer->id);
        $this->assertNotNull($pos);
        $this->assertNotNull($pos[0]);

        // Verify trail entry
        $trail = Redis::lrange(TrainerTrackingService::trailKey($this->trainer->id), 0, -1);
        $this->assertCount(1, $trail);
    }

    //  Status Transitions 

    public function test_it_transitions_at_office_to_en_route(): void
    {
        $client = Client::factory()->create(['assigned_sale_id' => $this->createUser(['role' => 'sale'])->id]);

        $response = $this->postJson('/api/v1/trainer/status', [
            'status' => 'en_route',
            'customer_id' => $client->id,
            'latitude' => 11.5564,
            'longitude' => 104.9160,
        ], $this->headers);

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Status updated.']);

        $this->assertDatabaseHas('trainer_activity_logs', [
            'trainer_id' => $this->trainer->id,
            'status' => 'en_route',
            'customer_id' => $client->id,
        ]);
    }

    public function test_it_rejects_invalid_transition_at_office_to_arrived(): void
    {
        $response = $this->postJson('/api/v1/trainer/status', [
            'status' => 'arrived',
            'latitude' => 11.5564,
            'longitude' => 104.9160,
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_it_transitions_en_route_to_arrived(): void
    {
        $client = Client::factory()->create(['assigned_sale_id' => $this->createUser(['role' => 'sale'])->id]);

        // First go en_route
        $statusService = app(TrainerStatusService::class);
        $statusService->changeStatus($this->trainer->id, 'en_route', [
            'customer_id' => $client->id,
        ]);

        $response = $this->postJson('/api/v1/trainer/status', [
            'status' => 'arrived',
            'customer_id' => $client->id,
            'latitude' => 11.5684,
            'longitude' => 104.9282,
        ], $this->headers);

        $response->assertOk();

        $this->assertDatabaseHas('trainer_activity_logs', [
            'trainer_id' => $this->trainer->id,
            'status' => 'arrived',
        ]);
    }

    public function test_it_transitions_arrived_to_in_session(): void
    {
        $client = Client::factory()->create(['assigned_sale_id' => $this->createUser(['role' => 'sale'])->id]);

        $statusService = app(TrainerStatusService::class);
        $statusService->changeStatus($this->trainer->id, 'en_route', ['customer_id' => $client->id]);
        $statusService->changeStatus($this->trainer->id, 'arrived', ['customer_id' => $client->id]);

        $response = $this->postJson('/api/v1/trainer/status', [
            'status' => 'in_session',
            'customer_id' => $client->id,
        ], $this->headers);

        $response->assertOk();

        $this->assertDatabaseHas('trainer_activity_logs', [
            'trainer_id' => $this->trainer->id,
            'status' => 'in_session',
        ]);
    }

    public function test_it_transitions_in_session_to_completed(): void
    {
        $client = Client::factory()->create(['assigned_sale_id' => $this->createUser(['role' => 'sale'])->id]);

        $statusService = app(TrainerStatusService::class);
        $statusService->changeStatus($this->trainer->id, 'en_route', ['customer_id' => $client->id]);
        $statusService->changeStatus($this->trainer->id, 'arrived', ['customer_id' => $client->id]);
        $statusService->changeStatus($this->trainer->id, 'in_session', ['customer_id' => $client->id]);

        $response = $this->postJson('/api/v1/trainer/status', [
            'status' => 'completed',
            'customer_id' => $client->id,
        ], $this->headers);

        $response->assertOk();

        $this->assertDatabaseHas('trainer_activity_logs', [
            'trainer_id' => $this->trainer->id,
            'status' => 'completed',
        ]);
    }

    public function test_it_transitions_completed_to_at_office(): void
    {
        $client = Client::factory()->create(['assigned_sale_id' => $this->createUser(['role' => 'sale'])->id]);

        $statusService = app(TrainerStatusService::class);
        $statusService->changeStatus($this->trainer->id, 'en_route', ['customer_id' => $client->id]);
        $statusService->changeStatus($this->trainer->id, 'arrived', ['customer_id' => $client->id]);
        $statusService->changeStatus($this->trainer->id, 'in_session', ['customer_id' => $client->id]);
        $statusService->changeStatus($this->trainer->id, 'completed', ['customer_id' => $client->id]);

        $response = $this->postJson('/api/v1/trainer/status', [
            'status' => 'at_office',
        ], $this->headers);

        $response->assertOk();

        $this->assertDatabaseHas('trainer_activity_logs', [
            'trainer_id' => $this->trainer->id,
            'status' => 'at_office',
        ]);
    }

    //  Geofence Auto-Arrival 

    public function test_it_auto_arrives_when_within_geofence_with_good_accuracy(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $client = Client::factory()->create([
            'assigned_sale_id' => $sale->id,
            'headquarter_latitude' => '11.5684',
            'headquarter_longitude' => '104.9282',
            'geofence_radius' => 200,
        ]);

        // Set trainer to en_route
        $statusService = app(TrainerStatusService::class);
        $statusService->changeStatus($this->trainer->id, 'en_route', [
            'customer_id' => $client->id,
        ]);

        // Send a ping very close to the customer (within 200m) with accuracy < 50m
        $response = $this->postJson('/api/v1/trainer/location-ping', [
            'latitude' => 11.5685,   // ~11m from customer
            'longitude' => 104.9283,
            'accuracy' => 10.0,
            'timestamp' => now()->toISOString(),
        ], $this->headers);

        $response->assertOk();

        // Should have auto-arrived
        $this->assertDatabaseHas('trainer_activity_logs', [
            'trainer_id' => $this->trainer->id,
            'status' => 'arrived',
            'detection_method' => 'geofence',
        ]);
    }

    public function test_it_does_not_auto_arrive_when_accuracy_too_low(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $client = Client::factory()->create([
            'assigned_sale_id' => $sale->id,
            'headquarter_latitude' => '11.5684',
            'headquarter_longitude' => '104.9282',
            'geofence_radius' => 200,
        ]);

        $statusService = app(TrainerStatusService::class);
        $statusService->changeStatus($this->trainer->id, 'en_route', [
            'customer_id' => $client->id,
        ]);

        // Ping close but with accuracy > 50m
        $this->postJson('/api/v1/trainer/location-ping', [
            'latitude' => 11.5685,
            'longitude' => 104.9283,
            'accuracy' => 60.0,     // Too inaccurate for geofence
            'timestamp' => now()->toISOString(),
        ], $this->headers);

        // Should NOT have auto-arrived
        $this->assertDatabaseMissing('trainer_activity_logs', [
            'trainer_id' => $this->trainer->id,
            'status' => 'arrived',
            'detection_method' => 'geofence',
        ]);
    }

    public function test_it_does_not_auto_arrive_when_outside_geofence(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $client = Client::factory()->create([
            'assigned_sale_id' => $sale->id,
            'headquarter_latitude' => '11.5684',
            'headquarter_longitude' => '104.9282',
            'geofence_radius' => 200,
        ]);

        $statusService = app(TrainerStatusService::class);
        $statusService->changeStatus($this->trainer->id, 'en_route', [
            'customer_id' => $client->id,
        ]);

        // Ping far from customer (~5km away)
        $this->postJson('/api/v1/trainer/location-ping', [
            'latitude' => 11.5200,
            'longitude' => 104.8900,
            'accuracy' => 10.0,
            'timestamp' => now()->toISOString(),
        ], $this->headers);

        // Should NOT have auto-arrived
        $this->assertDatabaseMissing('trainer_activity_logs', [
            'trainer_id' => $this->trainer->id,
            'status' => 'arrived',
            'detection_method' => 'geofence',
        ]);
    }
}
