<?php

namespace Tests\Feature\Sale;

use App\Services\Sale\SaleTrainerAssignmentService;
use Tests\TestCase;

class TrainerDropdownScopeTest extends TestCase
{
    /** @test */
    public function sale_only_sees_trainers_on_their_dedicated_roster(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $rosterTrainer = $this->createUser(['role' => 'trainer', 'first_name' => 'OnRoster']);
        $offRosterTrainer = $this->createUser(['role' => 'trainer', 'first_name' => 'OffRoster']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$rosterTrainer->id], $admin->id);

        $response = $this->getJson(
            '/api/v1/selection/trainers-dropdown',
            $this->authHeadersFor($sale),
        );

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($rosterTrainer->id, $ids);
        $this->assertNotContains($offRosterTrainer->id, $ids);
    }

    /** @test */
    public function sale_with_empty_roster_sees_no_trainers(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $this->createUser(['role' => 'trainer']);
        $this->createUser(['role' => 'trainer']);

        $response = $this->getJson(
            '/api/v1/selection/trainers-dropdown',
            $this->authHeadersFor($sale),
        );

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    /** @test */
    public function admin_sees_all_active_trainers(): void
    {
        $admin = $this->createAdmin();
        $t1 = $this->createUser(['role' => 'trainer']);
        $t2 = $this->createUser(['role' => 'trainer']);

        $response = $this->getJson(
            '/api/v1/selection/trainers-dropdown',
            $this->authHeadersFor($admin),
        );

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($t1->id, $ids);
        $this->assertContains($t2->id, $ids);
    }

    /** @test */
    public function suspended_trainers_are_excluded_from_dropdown_for_sale(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $active = $this->createUser(['role' => 'trainer']);
        $suspendedLater = $this->createUser(['role' => 'trainer']);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$active->id, $suspendedLater->id], $admin->id);

        // Suspend the second trainer directly via DB to bypass the lifecycle guard
        $suspendedLater->update(['is_suspended' => true]);

        $response = $this->getJson(
            '/api/v1/selection/trainers-dropdown',
            $this->authHeadersFor($sale),
        );

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($suspendedLater->id, $ids);
    }

    /** @test */
    public function sale_dropdown_excludes_trainers_removed_from_roster(): void
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
            '/api/v1/selection/trainers-dropdown',
            $this->authHeadersFor($sale),
        );

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($t1->id, $ids);
        $this->assertNotContains($t2->id, $ids);
    }
}
