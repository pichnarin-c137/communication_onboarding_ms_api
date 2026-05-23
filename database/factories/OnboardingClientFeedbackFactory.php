<?php

namespace Database\Factories;

use App\Models\OnboardingClientFeedback;
use App\Models\OnboardingRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingClientFeedback>
 */
class OnboardingClientFeedbackFactory extends Factory
{
    protected $model = OnboardingClientFeedback::class;

    public function definition(): array
    {
        return [
            'onboarding_id' => OnboardingRequest::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->sentence(),
            'submitted_via' => 'manual',
            'submitted_at' => now(),
        ];
    }

    public function low(): static
    {
        return $this->state(fn () => [
            'rating' => fake()->numberBetween(1, 2),
            'comment' => fake()->sentence(),
        ]);
    }
}
