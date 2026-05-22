<?php

namespace Tests\Feature\Sale;

use App\Exceptions\Business\TrainerNotInSaleRosterException;
use App\Models\Appointment;
use App\Models\Client;
use App\Services\Appointment\AppointmentService;
use App\Services\Sale\SaleTrainerAssignmentService;
use Carbon\Carbon;
use Tests\TestCase;

class AppointmentRosterEnforcementTest extends TestCase
{
    private function baseAppointmentPayload(string $clientId, ?string $trainerId): array
    {
        return [
            'title' => 'Training',
            'appointment_type' => 'training',
            'location_type' => 'online',
            'client_id' => $clientId,
            'trainer_id' => $trainerId,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
            'scheduled_start_time' => '10:00',
            'scheduled_end_time' => '11:00',
        ];
    }

    /** @test */
    public function sale_creating_appointment_with_roster_trainer_is_allowed(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $trainer = $this->createUser(['role' => 'trainer']);
        $client = Client::factory()->create(['assigned_sale_id' => $sale->id]);

        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$trainer->id], $admin->id);

        $appointment = app(AppointmentService::class)->create(
            $this->baseAppointmentPayload($client->id, $trainer->id),
            $sale->id,
        );

        $this->assertNotNull($appointment->id);
        $this->assertEquals($trainer->id, $appointment->trainer_id);
        $this->assertEquals($sale->id, $appointment->creator_id);
    }

    /** @test */
    public function sale_creating_appointment_with_off_roster_trainer_is_blocked(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $offRosterTrainer = $this->createUser(['role' => 'trainer']);
        $client = Client::factory()->create(['assigned_sale_id' => $sale->id]);

        $this->expectException(TrainerNotInSaleRosterException::class);

        app(AppointmentService::class)->create(
            $this->baseAppointmentPayload($client->id, $offRosterTrainer->id),
            $sale->id,
        );
    }

    /** @test */
    public function sale_creating_appointment_without_trainer_does_not_trigger_roster_check(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $client = Client::factory()->create(['assigned_sale_id' => $sale->id]);

        $appointment = app(AppointmentService::class)->create(
            $this->baseAppointmentPayload($client->id, null),
            $sale->id,
        );

        $this->assertNotNull($appointment->id);
        $this->assertNull($appointment->trainer_id);
    }

    /** @test */
    public function admin_updating_an_appointment_can_assign_off_roster_trainer(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $rosterTrainer = $this->createUser(['role' => 'trainer']);
        $offRosterTrainer = $this->createUser(['role' => 'trainer']);
        $client = Client::factory()->create(['assigned_sale_id' => $sale->id]);

        $admin = $this->createAdmin();
        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$rosterTrainer->id], $admin->id);

        $appointment = Appointment::create([
            'appointment_code' => 'APT-RT-'.uniqid(),
            'title' => 'Existing',
            'appointment_type' => 'training',
            'location_type' => 'online',
            'status' => 'pending',
            'trainer_id' => $rosterTrainer->id,
            'client_id' => $client->id,
            'creator_id' => $sale->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
            'scheduled_start_time' => '10:00',
            'scheduled_end_time' => '11:00',
        ]);

        $updated = app(AppointmentService::class)->update(
            $appointment,
            ['trainer_id' => $offRosterTrainer->id],
            'admin',
        );

        $this->assertEquals($offRosterTrainer->id, $updated->trainer_id);
    }

    /** @test */
    public function non_admin_updating_an_appointment_cannot_assign_off_roster_trainer(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $rosterTrainer = $this->createUser(['role' => 'trainer']);
        $offRosterTrainer = $this->createUser(['role' => 'trainer']);
        $client = Client::factory()->create(['assigned_sale_id' => $sale->id]);

        $admin = $this->createAdmin();
        app(SaleTrainerAssignmentService::class)
            ->replaceRoster($sale->id, [$rosterTrainer->id], $admin->id);

        $appointment = Appointment::create([
            'appointment_code' => 'APT-RT-'.uniqid(),
            'title' => 'Existing',
            'appointment_type' => 'training',
            'location_type' => 'online',
            'status' => 'pending',
            'trainer_id' => $rosterTrainer->id,
            'client_id' => $client->id,
            'creator_id' => $sale->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
            'scheduled_start_time' => '10:00',
            'scheduled_end_time' => '11:00',
        ]);

        $this->expectException(TrainerNotInSaleRosterException::class);

        app(AppointmentService::class)->update(
            $appointment,
            ['trainer_id' => $offRosterTrainer->id],
            'sale',
        );
    }
}
