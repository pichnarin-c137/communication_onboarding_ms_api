<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get the user role or create it if it doesn't exist
        $userRole = Role::where('role', 'user')->first();

        return [
            'role_id' => $userRole ? $userRole->id : Role::factory()->create(['role' => 'user'])->id,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'dob' => fake()->date('Y-m-d', '-18 years'),
            'address' => fake()->address(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'nationality' => fake()->country(),
            'is_suspended' => false,
        ];
    }

    /**
     * Indicate that the user has the admin role.
     */
    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            $adminRole = Role::where('role', 'admin')->first();

            return [
                'role_id' => $adminRole ? $adminRole->id : Role::factory()->create(['role' => 'admin'])->id,
            ];
        });
    }

    /**
     * Indicate that the user is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_suspended' => true,
        ]);
    }
}
