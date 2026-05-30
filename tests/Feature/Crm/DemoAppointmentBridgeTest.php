<?php

namespace Tests\Feature\Crm;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\Media;
use App\Models\User;
use App\Services\Appointment\AppointmentService;
use Tests\TestCase;

class DemoAppointmentBridgeTest extends TestCase
{
    private User $sale;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sale = $this->createUser(['role' => 'sale']);
        $this->headers = $this->authHeadersFor($this->sale);
    }

    private function contact(): CrmContact
    {
        return CrmContact::create([
            'company_name' => 'TechCorp Ltd',
            'contact_name' => 'John Doe',
            'phone' => '+855 12 345 678',
            'status' => 'prospect',
            'created_by' => $this->sale->id,
        ]);
    }

    private function deal(CrmContact $contact, string $stage = 'prospect'): CrmDeal
    {
        return CrmDeal::create([
            'crm_contact_id' => $contact->id,
            'title' => 'TechCorp HR Suite',
            'stage' => $stage,
            'assigned_to' => $this->sale->id,
            'created_by' => $this->sale->id,
        ]);
    }

    private function demoPayload(CrmContact $contact, ?CrmDeal $deal): array
    {
        return [
            'title' => 'Demo for TechCorp',
            'appointment_type' => 'demo',
            'location_type' => 'online',
            'crm_contact_id' => $contact->id,
            'crm_deal_id' => $deal?->id,
            'scheduled_date' => now()->toDateString(),
            'scheduled_start_time' => '09:00',
            'scheduled_end_time' => '10:00',
            'meeting_link' => 'https://meet.example.com/demo',
        ];
    }

    /** @test */
    public function booking_a_demo_against_a_prospect_creates_a_crm_linked_appointment_and_advances_the_deal(): void
    {
        $contact = $this->contact();
        $deal = $this->deal($contact);

        $response = $this->postJson('/api/v1/appointments', $this->demoPayload($contact, $deal), $this->headers);

        $response->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('appointments', [
            'appointment_type' => 'demo',
            'crm_contact_id' => $contact->id,
            'crm_deal_id' => $deal->id,
            'client_id' => null,
        ]);

        // Event → listener advanced the deal into the demo stage.
        $this->assertSame('demo_scheduled', $deal->fresh()->stage);
        $this->assertSame('deal_active', $contact->fresh()->status);
    }

    /** @test */
    public function booking_a_demo_does_not_downgrade_a_deal_already_further_along(): void
    {
        $contact = $this->contact();
        $deal = $this->deal($contact, 'negotiating');

        $this->postJson('/api/v1/appointments', $this->demoPayload($contact, $deal), $this->headers)
            ->assertCreated();

        $this->assertSame('negotiating', $deal->fresh()->stage);
    }

    /** @test */
    public function completing_a_demo_stamps_the_deal_and_notifies_the_owner(): void
    {
        $contact = $this->contact();
        $deal = $this->deal($contact, 'demo_scheduled');

        $appointment = Appointment::factory()->create([
            'appointment_type' => 'demo',
            'location_type' => 'online',
            'status' => 'in_progress',
            'client_id' => null,
            'crm_contact_id' => $contact->id,
            'crm_deal_id' => $deal->id,
            'trainer_id' => $this->sale->id,
            'creator_id' => $this->sale->id,
            'scheduled_date' => now()->toDateString(),
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '10:00:00',
            'actual_start_time' => now()->subHour(),
        ]);

        $media = Media::create([
            'filename' => 'proof.jpg',
            'original_filename' => 'proof.jpg',
            'file_path' => 'end_proof/proof.jpg',
            'file_url' => 'https://r2.example.com/end_proof/proof.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'media_category' => 'other',
            'uploaded_by_user_id' => $this->sale->id,
        ]);

        app(AppointmentService::class)->completeAppointment(
            $appointment, $media->id, 11.55, 104.91, 3, 'Demo went well', $this->sale->id
        );

        $deal->refresh();
        $this->assertNotNull($deal->demo_completed_at);
        $this->assertSame('demo_scheduled', $deal->stage, 'Deal stays in the funnel after the demo.');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->sale->id,
            'type' => 'crm_demo_completed',
        ]);
    }

    /** @test */
    public function a_training_appointment_cannot_carry_crm_links(): void
    {
        $client = Client::factory()->create(['assigned_sale_id' => $this->sale->id]);
        $contact = $this->contact();

        $this->postJson('/api/v1/appointments', [
            'appointment_type' => 'training',
            'location_type' => 'online',
            'client_id' => $client->id,
            'crm_contact_id' => $contact->id,
            'scheduled_date' => now()->toDateString(),
            'scheduled_start_time' => '09:00',
            'scheduled_end_time' => '10:00',
        ], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['crm_contact_id']);
    }

    /** @test */
    public function a_demo_must_target_exactly_one_of_client_or_contact(): void
    {
        $client = Client::factory()->create(['assigned_sale_id' => $this->sale->id]);
        $contact = $this->contact();

        // Both set → rejected.
        $this->postJson('/api/v1/appointments', [
            'title' => 'Demo',
            'appointment_type' => 'demo',
            'location_type' => 'online',
            'client_id' => $client->id,
            'crm_contact_id' => $contact->id,
            'scheduled_date' => now()->toDateString(),
            'scheduled_start_time' => '09:00',
            'scheduled_end_time' => '10:00',
        ], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['crm_contact_id']);

        // Neither set → rejected.
        $this->postJson('/api/v1/appointments', [
            'title' => 'Demo',
            'appointment_type' => 'demo',
            'location_type' => 'online',
            'scheduled_date' => now()->toDateString(),
            'scheduled_start_time' => '09:00',
            'scheduled_end_time' => '10:00',
        ], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['crm_contact_id']);
    }

    /** @test */
    public function a_demo_to_an_existing_client_still_works_without_crm_links(): void
    {
        $client = Client::factory()->create(['assigned_sale_id' => $this->sale->id]);

        $this->postJson('/api/v1/appointments', [
            'title' => 'Demo for existing client',
            'appointment_type' => 'demo',
            'location_type' => 'online',
            'client_id' => $client->id,
            'scheduled_date' => now()->toDateString(),
            'scheduled_start_time' => '09:00',
            'scheduled_end_time' => '10:00',
        ], $this->headers)
            ->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'appointment_type' => 'demo',
            'client_id' => $client->id,
            'crm_contact_id' => null,
        ]);
    }
}
