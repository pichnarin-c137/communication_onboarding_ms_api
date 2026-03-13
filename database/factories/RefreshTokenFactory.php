<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RefreshToken>
 */
class RefreshTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => 'test_refresh_token_'.fake()->uuid(),
            'expires_at' => Carbon::now()->addDays(30),
            'is_revoked' => false,
        ];
    }

    /**
     * Indicate that the token is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => Carbon::now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the token is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_revoked' => true,
        ]);
    }
}
