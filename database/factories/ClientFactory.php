<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'code' => 'CLT-' . fake()->unique()->numerify('####'),
            'company_code' => 'REG-KH-' . fake()->numerify('####-###'),
            'company_name' => fake()->company(),
            'phone_number' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'headquarter_address' => fake()->address(),
            'headquarter_latitude' => fake()->latitude(11.50, 11.60),
            'headquarter_longitude' => fake()->longitude(104.85, 104.95),
            'social_links' => json_encode(['facebook' => 'https://facebook.com/' . fake()->slug()]),
            'is_active' => true,
            'geofence_radius' => 200,
        ];
    }
}
