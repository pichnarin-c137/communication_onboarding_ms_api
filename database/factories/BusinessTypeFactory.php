<?php

namespace Database\Factories;

use App\Models\BusinessType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessType>
 */
class BusinessTypeFactory extends Factory
{
    protected $model = BusinessType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name_en' => ucfirst($name),
            'name_km' => 'ប្រភេទ '.$name,
        ];
    }
}
