<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', '+30 days');
        $startHour = fake()->numberBetween(8, 16);
        $startTime = sprintf('%02d:00:00', $startHour);
        $endTime = sprintf('%02d:00:00', $startHour + 1);

        return [
            'title' => fake()->sentence(3),
            'appointment_type' => fake()->randomElement(['training', 'demo']),
            'location_type' => fake()->randomElement(['online', 'physical', 'hybrid']),
            'status' => 'pending',
            'trainer_id' => User::factory(),
            'client_id' => Client::factory(),
            'creator_id' => User::factory(),
            'scheduled_date' => $date->format('Y-m-d'),
            'scheduled_start_time' => $startTime,
            'scheduled_end_time' => $endTime,
            'student_count' => 0,
            'is_onboarding_triggered' => false,
            'is_continued_session' => false,
        ];
    }

    public function done(): static
    {
        return $this->state(function (array $attributes) {
            $date = $attributes['scheduled_date'];
            $start = $attributes['scheduled_start_time'];

            return [
                'status' => 'done',
                'actual_start_time' => $date.' '.$start,
                'actual_end_time' => date('Y-m-d H:i:s', strtotime($date.' '.$start) + 3600),
                'student_count' => fake()->numberBetween(1, 12),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    public function demo(): static
    {
        return $this->state(fn () => ['appointment_type' => 'demo']);
    }

    public function training(): static
    {
        return $this->state(fn () => ['appointment_type' => 'training']);
    }

    public function onTime(): static
    {
        return $this->state(function (array $attributes) {
            $scheduled = $attributes['scheduled_date'].' '.$attributes['scheduled_start_time'];

            return [
                'status' => 'done',
                'actual_start_time' => $scheduled,
                'actual_end_time' => date('Y-m-d H:i:s', strtotime($scheduled) + 3600),
                'student_count' => fake()->numberBetween(1, 10),
            ];
        });
    }

    public function late(): static
    {
        return $this->state(function (array $attributes) {
            $scheduled = strtotime($attributes['scheduled_date'].' '.$attributes['scheduled_start_time']);

            return [
                'status' => 'done',
                'actual_start_time' => date('Y-m-d H:i:s', $scheduled + 30 * 60),
                'actual_end_time' => date('Y-m-d H:i:s', $scheduled + 90 * 60),
                'student_count' => fake()->numberBetween(1, 10),
            ];
        });
    }

    public function noShow(): static
    {
        return $this->state(function (array $attributes) {
            $scheduled = $attributes['scheduled_date'].' '.$attributes['scheduled_start_time'];

            return [
                'status' => 'done',
                'actual_start_time' => $scheduled,
                'actual_end_time' => date('Y-m-d H:i:s', strtotime($scheduled) + 600),
                'student_count' => 0,
            ];
        });
    }
}
