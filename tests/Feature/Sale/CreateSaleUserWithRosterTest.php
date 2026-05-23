<?php

namespace Tests\Feature\Sale;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\SaleTrainerAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CreateSaleUserWithRosterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function userPayload(string $role, array $trainerIds = []): array
    {
        return [
            'first_name' => 'New',
            'last_name' => 'Person',
            'gender' => 'female',
            'nationality' => 'Khmer',
            'role' => $role,
            'email' => fake()->unique()->safeEmail(),
            'username' => 'user_'.uniqid(),
            'phone_number' => '+855'.fake()->unique()->numerify('########'),
            'password' => 'Password123!',
            'trainer_ids' => $trainerIds,
        ];
    }

    /** @test */
    public function admin_creates_sale_with_trainer_roster_successfully(): void
    {
        $admin = $this->createAdmin();
        $t1 = $this->createUser(['role' => 'trainer']);
        $t2 = $this->createUser(['role' => 'trainer']);

        $response = $this->postJson(
            '/api/v1/create-user',
            $this->userPayload('sale', [$t1->id, $t2->id]),
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.dedicated_trainers.0.trainer_user_id', $t1->id)
            ->assertJsonPath('data.dedicated_trainers.1.trainer_user_id', $t2->id);

        $saleUserId = $response->json('data.user_id');

        $this->assertDatabaseHas('sale_trainer_assignments', [
            'sale_user_id' => $saleUserId,
            'trainer_user_id' => $t1->id,
            'assigned_by_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('sale_trainer_assignments', [
            'sale_user_id' => $saleUserId,
            'trainer_user_id' => $t2->id,
        ]);
    }

    /** @test */
    public function admin_cannot_create_sale_without_trainer_ids(): void
    {
        $admin = $this->createAdmin();

        $payload = $this->userPayload('sale');
        unset($payload['trainer_ids']);

        $response = $this->postJson('/api/v1/create-user', $payload, $this->authHeadersFor($admin));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['trainer_ids']);
    }

    /** @test */
    public function admin_cannot_create_sale_with_empty_trainer_array(): void
    {
        $admin = $this->createAdmin();
        config(['coms.sale_roster.min_trainers' => 1]);

        $response = $this->postJson(
            '/api/v1/create-user',
            $this->userPayload('sale', []),
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trainer_ids']);
    }

    /** @test */
    public function admin_cannot_assign_a_non_trainer_user(): void
    {
        $admin = $this->createAdmin();
        $someoneNotTrainer = $this->createUser(['role' => 'sale']);

        $response = $this->postJson(
            '/api/v1/create-user',
            $this->userPayload('sale', [$someoneNotTrainer->id]),
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_USER_ROLE_FOR_ROSTER');
    }

    /** @test */
    public function admin_cannot_assign_a_suspended_trainer(): void
    {
        $admin = $this->createAdmin();
        $suspendedTrainer = $this->createUser(['role' => 'trainer', 'is_suspended' => true]);

        $response = $this->postJson(
            '/api/v1/create-user',
            $this->userPayload('sale', [$suspendedTrainer->id]),
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'SUSPENDED_OR_DELETED_TRAINER_CANNOT_BE_ASSIGNED');
    }

    /** @test */
    public function admin_cannot_assign_a_trainer_over_pending_appointment_cap(): void
    {
        $admin = $this->createAdmin();
        $trainer = $this->createUser(['role' => 'trainer']);
        $client = Client::factory()->create([
            'assigned_sale_id' => $this->createUser(['role' => 'sale'])->id,
        ]);

        config(['coms.sale_roster.max_pending_appointments_per_trainer' => 1]);

        Appointment::create([
            'title' => 'Existing Pending',
            'appointment_type' => 'training',
            'location_type' => 'online',
            'status' => 'pending',
            'trainer_id' => $trainer->id,
            'client_id' => $client->id,
            'creator_id' => $admin->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
            'scheduled_start_time' => '10:00',
            'scheduled_end_time' => '11:00',
            'appointment_code' => 'APT-TST-'.uniqid(),
        ]);

        $response = $this->postJson(
            '/api/v1/create-user',
            $this->userPayload('sale', [$trainer->id]),
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'TRAINER_WORKLOAD_EXCEEDED');
    }

    /** @test */
    public function non_sale_role_does_not_require_trainer_ids(): void
    {
        $admin = $this->createAdmin();

        $payload = $this->userPayload('trainer');
        unset($payload['trainer_ids']);

        $response = $this->postJson('/api/v1/create-user', $payload, $this->authHeadersFor($admin));

        $response->assertStatus(201)
            ->assertJsonPath('data.dedicated_trainers', null);
        $this->assertEquals(0, SaleTrainerAssignment::count());
    }
}
