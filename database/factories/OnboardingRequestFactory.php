<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\OnboardingRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingRequest>
 */
class OnboardingRequestFactory extends Factory
{
    protected $model = OnboardingRequest::class;

    public function definition(): array
    {
        return [
            'request_code' => 'ONB-'.fake()->unique()->numerify('######'),
            'appointment_id' => Appointment::factory(),
            'client_id' => Client::factory(),
            'trainer_id' => User::factory(),
            'status' => 'pending',
            'progress_percentage' => 0,
            'hold_count' => 0,
            'cycle_number' => 1,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => 'in_progress',
            'progress_percentage' => fake()->numberBetween(10, 80),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);
    }

    public function onHold(): static
    {
        return $this->state(fn () => [
            'status' => 'on_hold',
            'hold_reason' => fake()->sentence(),
            'hold_started_at' => now()->subDays(2),
            'hold_count' => 1,
        ]);
    }
}
