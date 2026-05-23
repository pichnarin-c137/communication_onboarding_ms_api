<?php

namespace Database\Factories;

use App\Models\SaleTrainerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleTrainerAssignment>
 */
class SaleTrainerAssignmentFactory extends Factory
{
    protected $model = SaleTrainerAssignment::class;

    public function definition(): array
    {
        return [
            'sale_user_id' => User::factory(),
            'trainer_user_id' => User::factory(),
            'assigned_at' => now(),
        ];
    }
}
