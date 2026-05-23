<?php

namespace Database\Factories;

use App\Models\OnboardingRequest;
use App\Models\OnboardingTrainerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingTrainerAssignment>
 */
class OnboardingTrainerAssignmentFactory extends Factory
{
    protected $model = OnboardingTrainerAssignment::class;

    public function definition(): array
    {
        return [
            'onboarding_id' => OnboardingRequest::factory(),
            'trainer_id' => User::factory(),
            'assigned_at' => now(),
            'is_current' => true,
        ];
    }
}
