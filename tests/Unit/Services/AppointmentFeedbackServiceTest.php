<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\AppointmentFeedback;
use App\Models\AppointmentFeedbackToken;
use App\Models\Client;
use App\Models\FeedbackRespondent;
use App\Models\System;
use App\Services\Appointment\AppointmentFeedbackService;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentFeedbackServiceTest extends TestCase
{
    private AppointmentFeedbackService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AppointmentFeedbackService::class);
    }

    public function test_it_returns_the_overall_rating_as_a_fixed_two_decimal_string(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $trainer = $this->createUser(['role' => 'trainer']);
        $client = Client::factory()->create(['assigned_sale_id' => $sale->id]);
        $system = System::where('name', 'coms')->firstOrFail();

        $appointment = Appointment::create([
            'appointment_code' => 'APT-TEST-' . Str::random(6),
            'title' => 'Rating Test',
            'system_id' => $system->id,
            'appointment_type' => 'training',
            'location_type' => 'online',
            'status' => 'done',
            'trainer_id' => $trainer->id,
            'client_id' => $client->id,
            'creator_id' => $sale->id,
            'scheduled_date' => now()->toDateString(),
            'scheduled_start_time' => '10:00:00',
            'scheduled_end_time' => '11:00:00',
            'student_count' => 0,
            'is_onboarding_triggered' => true,
            'is_continued_session' => false,
        ]);

        $tokenOne = AppointmentFeedbackToken::create([
            'appointment_id' => $appointment->id,
            'token' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $tokenTwo = AppointmentFeedbackToken::create([
            'appointment_id' => $appointment->id,
            'token' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $respondentOne = FeedbackRespondent::create([
            'client_id' => $client->id,
            'name' => 'Test Respondent One',
            'email' => 'respondent1@example.com',
        ]);

        $respondentTwo = FeedbackRespondent::create([
            'client_id' => $client->id,
            'name' => 'Test Respondent Two',
            'email' => 'respondent2@example.com',
        ]);

        AppointmentFeedback::create([
            'appointment_id' => $appointment->id,
            'token_id' => $tokenOne->id,
            'respondent_id' => $respondentOne->id,
            'rating' => 1,
            'comment' => null,
            'submitted_at' => now()->subMinutes(2),
        ]);

        AppointmentFeedback::create([
            'appointment_id' => $appointment->id,
            'token_id' => $tokenTwo->id,
            'respondent_id' => $respondentTwo->id,
            'rating' => 3,
            'comment' => null,
            'submitted_at' => now()->subMinute(),
        ]);

        $tokenThree = AppointmentFeedbackToken::create([
            'appointment_id' => $appointment->id,
            'token' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $respondentThree = FeedbackRespondent::create([
            'client_id' => $client->id,
            'name' => 'Test Respondent Three',
            'email' => 'respondent3@example.com',
        ]);

        AppointmentFeedback::create([
            'appointment_id' => $appointment->id,
            'token_id' => $tokenThree->id,
            'respondent_id' => $respondentThree->id,
            'rating' => 3,
            'comment' => null,
            'submitted_at' => now(),
        ]);

        $rating = $this->service->getOverallRating($appointment->id);

        $this->assertSame('2.33', $rating);
    }
}
