<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Credential>
 */
class CredentialFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'phone_number' => '+1'.fake()->numerify('##########'),
            'password' => static::$password ??= Hash::make('Test@12345'),
            'otp' => null,
            'otp_expiry' => null,
        ];
    }

    /**
     * Indicate that the credential has a valid OTP.
     */
    public function withOtp(string $otp = '1234', int $expiryMinutes = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'otp' => $otp,
            'otp_expiry' => Carbon::now()->addMinutes($expiryMinutes),
        ]);
    }

    /**
     * Indicate that the credential has an expired OTP.
     */
    public function withExpiredOtp(string $otp = '1234'): static
    {
        return $this->state(fn (array $attributes) => [
            'otp' => $otp,
            'otp_expiry' => Carbon::now()->subMinutes(5),
        ]);
    }
}
