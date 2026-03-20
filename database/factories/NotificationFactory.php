<?php

namespace Database\Factories;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id'             => (string) Str::uuid(),
            'client_contact_id'   => null,
            'type'                => 'demo_completed',
            'title'               => $this->faker->sentence(4),
            'message'             => $this->faker->sentence(10),
            'related_entity_type' => 'appointment',
            'related_entity_id'   => (string) Str::uuid(),
            'is_read'             => false,
            'read_at'             => null,
        ];
    }
}
