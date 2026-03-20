<?php

namespace Tests\Unit\Services;

use App\Events\NotificationCreated;
use App\Models\Client;
use App\Models\ClientContact;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationService::class);
    }

    /** @test */
    public function it_creates_one_notification_row_per_user(): void
    {
        Event::fake([NotificationCreated::class]);
        $user1 = $this->createUser(['role' => 'sale']);
        $user2 = $this->createUser(['role' => 'trainer']);

        $this->service->notify([$user1->id, $user2->id], 'demo_completed', 'Done', 'Complete.');

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', ['user_id' => $user1->id, 'type' => 'demo_completed']);
        $this->assertDatabaseHas('notifications', ['user_id' => $user2->id, 'type' => 'demo_completed']);
    }

    /** @test */
    public function it_dispatches_notification_created_event_for_each_user(): void
    {
        Event::fake([NotificationCreated::class]);
        $user1 = $this->createUser(['role' => 'sale']);
        $user2 = $this->createUser(['role' => 'trainer']);

        $this->service->notify([$user1->id, $user2->id], 'demo_completed', 'Done', 'Complete.');

        Event::assertDispatched(NotificationCreated::class, 2);
    }

    /** @test */
    public function it_dispatches_event_with_the_correct_notification_model(): void
    {
        Event::fake([NotificationCreated::class]);
        $user = $this->createUser(['role' => 'sale']);

        $this->service->notify([$user->id], 'demo_completed', 'Done', 'Complete.');

        Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) use ($user) {
            return $event->notification->user_id === $user->id
                && $event->notification->type === 'demo_completed';
        });
    }

    /** @test */
    public function it_does_not_dispatch_events_when_user_ids_is_empty(): void
    {
        Event::fake([NotificationCreated::class]);

        $this->service->notify([], 'demo_completed', 'Done', 'Complete.');

        Event::assertNotDispatched(NotificationCreated::class);
        $this->assertDatabaseCount('notifications', 0);
    }

    /** @test */
    public function contact_notifications_do_not_dispatch_broadcast_events(): void
    {
        Event::fake([NotificationCreated::class]);

        $sale    = $this->createUser(['role' => 'sale']);
        $client  = Client::create([
            'code'            => 'TST-' . strtoupper(Str::random(4)),
            'company_name'    => 'Test Company',
            'is_active'       => true,
            'assigned_sale_id' => $sale->id,
        ]);
        $contact = ClientContact::create([
            'client_id' => $client->id,
            'name'      => 'Test Contact',
        ]);

        $this->service->notifyContact([$contact->id], 'lesson_sent', 'Sent', 'Lesson delivered.');

        Event::assertNotDispatched(NotificationCreated::class);
    }

    /** @test */
    public function it_persists_related_entity_on_notification(): void
    {
        Event::fake([NotificationCreated::class]);
        $user     = $this->createUser(['role' => 'sale']);
        $entityId = (string) Str::uuid();

        $this->service->notify([$user->id], 'onboarding_created', 'Started', 'Begun.', [
            'type' => 'onboarding_request',
            'id'   => $entityId,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id'             => $user->id,
            'related_entity_type' => 'onboarding_request',
            'related_entity_id'   => $entityId,
        ]);
    }
}
