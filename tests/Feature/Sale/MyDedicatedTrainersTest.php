<?php

namespace Tests\Feature\Sale;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\OnboardingRequest;
use App\Services\Sale\SaleTrainerAssignmentService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class MyDedicatedTrainersTest extends TestCase
{
    /** @test */
    public function sale_lists_their_dedicated_trainers_with_enriched_workload(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $trainer = $this->createUser(['role' => 'trainer']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$trainer->id], $admin->id);

        $client = Client::factory()->create(['assigned_sale_id' => $sale->id]);
        Appointment::create([
            'appointment_code' => 'APT-'.Str::random(4),
            'title' => 'Test',
            'appointment_type' => 'training',
            'location_type' => 'online',
            'status' => 'pending',
            'trainer_id' => $trainer->id,
            'client_id' => $client->id,
            'creator_id' => $sale->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
            'scheduled_start_time' => '10:00',
            'scheduled_end_time' => '11:00',
        ]);

        $response = $this->getJson('/api/v1/me/dedicated-trainers', $this->authHeadersFor($sale));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.trainer_user_id', $trainer->id)
            ->assertJsonPath('data.0.workload.pending_appointments', 1)
            ->assertJsonPath('data.0.workload.active_onboardings', 0);
        $this->assertNotNull($response->json('data.0.live_status'));
    }

    /** @test */
    public function sale_with_empty_roster_gets_empty_list(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $this->createUser(['role' => 'trainer']);

        $response = $this->getJson('/api/v1/me/dedicated-trainers', $this->authHeadersFor($sale));

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    /** @test */
    public function non_sale_role_cannot_access_me_dedicated_trainers(): void
    {
        $admin = $this->createAdmin();

        $response = $this->getJson('/api/v1/me/dedicated-trainers', $this->authHeadersFor($admin));

        $response->assertStatus(403);
    }

    /** @test */
    public function sale_gets_overview_for_roster_trainer(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $trainer = $this->createUser(['role' => 'trainer']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$trainer->id], $admin->id);

        $response = $this->getJson(
            "/api/v1/me/dedicated-trainers/$trainer->id/overview",
            $this->authHeadersFor($sale),
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.trainer.trainer_user_id', $trainer->id)
            ->assertJsonPath('data.live.status', 'at_office')
            ->assertJsonPath('data.workload.max_sales_per_trainer', 3)
            ->assertJsonPath('data.summary_30d.completed_appointments', 0);
    }

    /** @test */
    public function sale_overview_for_off_roster_trainer_returns_403(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $offRosterTrainer = $this->createUser(['role' => 'trainer']);

        $response = $this->getJson(
            "/api/v1/me/dedicated-trainers/$offRosterTrainer->id/overview",
            $this->authHeadersFor($sale),
        );

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'TRAINER_NOT_IN_SALE_ROSTER');
    }

    /** @test */
    public function appointments_list_filter_by_trainer_id_is_gated_for_sale(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $rosterTrainer = $this->createUser(['role' => 'trainer']);
        $offRosterTrainer = $this->createUser(['role' => 'trainer']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$rosterTrainer->id], $admin->id);

        // Allowed: filter by roster trainer
        $okResponse = $this->getJson(
            "/api/v1/appointments?trainer_id=$rosterTrainer->id",
            $this->authHeadersFor($sale),
        );
        $okResponse->assertStatus(200);

        // Blocked: filter by off-roster trainer
        $blockResponse = $this->getJson(
            "/api/v1/appointments?trainer_id=$offRosterTrainer->id",
            $this->authHeadersFor($sale),
        );
        $blockResponse->assertStatus(403)
            ->assertJsonPath('error_code', 'TRAINER_NOT_IN_SALE_ROSTER');
    }

    /** @test */
    public function onboarding_list_filter_by_trainer_id_is_gated_for_sale(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $rosterTrainer = $this->createUser(['role' => 'trainer']);
        $offRosterTrainer = $this->createUser(['role' => 'trainer']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$rosterTrainer->id], $admin->id);

        $okResponse = $this->getJson(
            "/api/v1/onboarding?trainer_id=$rosterTrainer->id",
            $this->authHeadersFor($sale),
        );
        $okResponse->assertStatus(200);

        $blockResponse = $this->getJson(
            "/api/v1/onboarding?trainer_id=$offRosterTrainer->id",
            $this->authHeadersFor($sale),
        );
        $blockResponse->assertStatus(403)
            ->assertJsonPath('error_code', 'TRAINER_NOT_IN_SALE_ROSTER');
    }

    /** @test */
    public function enriched_list_includes_last_interaction_at(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $trainer = $this->createUser(['role' => 'trainer']);
        $client = Client::factory()->create(['assigned_sale_id' => $sale->id]);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$trainer->id], $admin->id);

        Appointment::create([
            'appointment_code' => 'APT-'.Str::random(4),
            'title' => 'Test',
            'appointment_type' => 'training',
            'location_type' => 'online',
            'status' => 'pending',
            'trainer_id' => $trainer->id,
            'client_id' => $client->id,
            'creator_id' => $sale->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
            'scheduled_start_time' => '10:00',
            'scheduled_end_time' => '11:00',
        ]);

        $response = $this->getJson('/api/v1/me/dedicated-trainers', $this->authHeadersFor($sale));

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.0.last_interaction_at'));
    }
}
