<?php

namespace Database\Factories;

use App\Models\PersonalInformation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalInformation>
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
            'user_id' => User::factory(),
            'professtional_photo' => 'documents/professtional_photos/'.fake()->uuid().'.jpg',
            'nationality_card' => 'documents/nationality_cards/'.fake()->uuid().'.pdf',
            'family_book' => 'documents/family_books/'.fake()->uuid().'.pdf',
            'birth_certificate' => 'documents/birth_certificates/'.fake()->uuid().'.pdf',
            'degreee_certificate' => 'documents/degree_certificates/'.fake()->uuid().'.pdf',
            'social_media' => fake()->url(),
        ];
    }
}
