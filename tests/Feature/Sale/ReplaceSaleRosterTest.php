<?php

namespace Tests\Feature\Sale;

use App\Models\SaleTrainerAssignment;
use App\Services\Sale\SaleTrainerAssignmentService;
use Tests\TestCase;

class ReplaceSaleRosterTest extends TestCase
{
    /** @test */
    public function admin_can_get_an_empty_roster_for_a_sale_user(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);

        $response = $this->getJson(
            "/api/v1/users/$sale->id/dedicated-trainers",
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sale_user_id', $sale->id)
            ->assertJsonPath('data.dedicated_trainers', []);
    }

    /** @test */
    public function admin_can_replace_a_sale_roster_and_response_returns_diff(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $t1 = $this->createUser(['role' => 'trainer']);
        $t2 = $this->createUser(['role' => 'trainer']);
        $t3 = $this->createUser(['role' => 'trainer']);

        // Seed initial roster with [t1, t2]
        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$t1->id, $t2->id], $admin->id);

        // Now PUT [t1, t3] — should add t3, remove t2, keep t1
        $response = $this->putJson(
            "/api/v1/users/$sale->id/dedicated-trainers",
            ['trainer_ids' => [$t1->id, $t3->id]],
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.added', [$t3->id])
            ->assertJsonPath('data.removed', [$t2->id]);

        $this->assertDatabaseHas('sale_trainer_assignments', [
            'sale_user_id' => $sale->id,
            'trainer_user_id' => $t1->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('sale_trainer_assignments', [
            'sale_user_id' => $sale->id,
            'trainer_user_id' => $t3->id,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('sale_trainer_assignments', [
            'sale_user_id' => $sale->id,
            'trainer_user_id' => $t2->id,
        ]);
    }

    /** @test */
    public function reput_of_same_roster_is_a_noop(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $t1 = $this->createUser(['role' => 'trainer']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$t1->id], $admin->id);

        $response = $this->putJson(
            "/api/v1/users/$sale->id/dedicated-trainers",
            ['trainer_ids' => [$t1->id]],
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.added', [])
            ->assertJsonPath('data.removed', []);

        $this->assertEquals(1, SaleTrainerAssignment::count());
    }

    /** @test */
    public function previously_removed_trainer_is_restored_via_undelete_on_replace(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $t1 = $this->createUser(['role' => 'trainer']);

        // Add then remove
        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$t1->id], $admin->id);
        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [], $admin->id);

        $this->assertSoftDeleted('sale_trainer_assignments', [
            'sale_user_id' => $sale->id,
            'trainer_user_id' => $t1->id,
        ]);

        // Re-add via PUT — should restore the soft-deleted row, not insert duplicate
        $response = $this->putJson(
            "/api/v1/users/$sale->id/dedicated-trainers",
            ['trainer_ids' => [$t1->id]],
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(200);

        $this->assertEquals(1, SaleTrainerAssignment::withTrashed()
            ->where('sale_user_id', $sale->id)
            ->where('trainer_user_id', $t1->id)
            ->count());
        $this->assertDatabaseHas('sale_trainer_assignments', [
            'sale_user_id' => $sale->id,
            'trainer_user_id' => $t1->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function non_admin_cannot_replace_a_roster(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $otherSale = $this->createUser(['role' => 'sale']);
        $trainer = $this->createUser(['role' => 'trainer']);

        $response = $this->putJson(
            "/api/v1/users/$otherSale->id/dedicated-trainers",
            ['trainer_ids' => [$trainer->id]],
            $this->authHeadersFor($sale),
        );

        $response->assertStatus(403);
    }

    /** @test */
    public function get_roster_filters_out_soft_deleted_rows(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $t1 = $this->createUser(['role' => 'trainer']);
        $t2 = $this->createUser(['role' => 'trainer']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$t1->id, $t2->id], $admin->id);
        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$t1->id], $admin->id);

        $response = $this->getJson(
            "/api/v1/users/$sale->id/dedicated-trainers",
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.dedicated_trainers'));
        $this->assertEquals($t1->id, $response->json('data.dedicated_trainers.0.trainer_user_id'));
    }
}
