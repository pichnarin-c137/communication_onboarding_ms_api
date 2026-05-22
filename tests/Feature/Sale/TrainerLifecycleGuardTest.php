<?php

namespace Tests\Feature\Sale;

use App\Models\SaleTrainerAssignment;
use App\Services\Sale\SaleTrainerAssignmentService;
use Tests\TestCase;

class TrainerLifecycleGuardTest extends TestCase
{
    /** @test */
    public function suspending_a_trainer_on_a_roster_is_blocked(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $trainer = $this->createUser(['role' => 'trainer']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$trainer->id], $admin->id);

        $response = $this->patchJson(
            "/api/v1/users/$trainer->id/suspend",
            [],
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'TRAINER_HAS_ACTIVE_COMMITMENTS');
        $this->assertDatabaseHas('users', ['id' => $trainer->id, 'is_suspended' => false]);
    }

    /** @test */
    public function suspending_a_trainer_with_no_active_work_is_allowed(): void
    {
        $admin = $this->createAdmin();
        $trainer = $this->createUser(['role' => 'trainer']);

        $response = $this->patchJson(
            "/api/v1/users/$trainer->id/suspend",
            [],
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $trainer->id, 'is_suspended' => true]);
    }

    /** @test */
    public function soft_deleting_a_trainer_on_a_roster_is_blocked(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $trainer = $this->createUser(['role' => 'trainer']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$trainer->id], $admin->id);

        $response = $this->deleteJson(
            "/api/v1/soft-delete-user/$trainer->id",
            [],
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'TRAINER_HAS_ACTIVE_COMMITMENTS');
        $this->assertDatabaseHas('users', ['id' => $trainer->id, 'deleted_at' => null]);
    }

    /** @test */
    public function soft_deleting_a_sale_cascades_their_roster(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $t1 = $this->createUser(['role' => 'trainer']);
        $t2 = $this->createUser(['role' => 'trainer']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$t1->id, $t2->id], $admin->id);

        $response = $this->deleteJson(
            "/api/v1/soft-delete-user/$sale->id",
            [],
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(200);

        $this->assertEquals(0, SaleTrainerAssignment::query()
            ->where('sale_user_id', $sale->id)
            ->count());
        $this->assertEquals(2, SaleTrainerAssignment::withTrashed()
            ->where('sale_user_id', $sale->id)
            ->whereNotNull('deleted_at')
            ->count());
    }
}
