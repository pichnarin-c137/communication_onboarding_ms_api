<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PersonalInformation>
 */
class PersonalInformationFactory extends Factory
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
            'professtional_photo' => 'documents/professtional_photos/'.fake()->uuid().'.jpg',
            'nationality_card' => 'documents/nationality_cards/'.fake()->uuid().'.pdf',
            'family_book' => 'documents/family_books/'.fake()->uuid().'.pdf',
            'birth_certificate' => 'documents/birth_certificates/'.fake()->uuid().'.pdf',
            'degreee_certificate' => 'documents/degree_certificates/'.fake()->uuid().'.pdf',
            'social_media' => fake()->url(),
        ];
    }
}
