<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmergencyContact>
 */
class EmergencyContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'contact_first_name' => fake()->firstName(),
            'contact_last_name' => fake()->lastName(),
            'contact_relationship' => fake()->randomElement(['Spouse', 'Parent', 'Sibling', 'Friend', 'Relative']),
            'contact_phone_number' => fake()->phoneNumber(),
            'contact_address' => fake()->address(),
            'contact_social_media' => fake()->url(),
        ];
    }
}
