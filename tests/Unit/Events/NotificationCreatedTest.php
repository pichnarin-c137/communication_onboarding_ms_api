<?php

namespace Tests\Unit\Events;

use App\Events\NotificationCreated;
use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationCreatedTest extends TestCase
{
    #[Test]
    public function it_implements_should_broadcast(): void
    {
        $notification = Notification::factory()->make(['user_id' => 'user-uuid-123']);
        $this->assertInstanceOf(ShouldBroadcast::class, new NotificationCreated($notification));
    }

    #[Test]
    public function it_broadcasts_on_private_channel_for_the_notification_owner(): void
    {
        $notification = Notification::factory()->make(['user_id' => 'user-uuid-123']);
        $event = new NotificationCreated($notification);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        // PrivateChannel prepends 'private-' to the name internally
        $this->assertEquals('private-notifications.user-uuid-123', $channels[0]->name);
    }

    #[Test]
    public function it_broadcasts_with_expected_payload_keys(): void
    {
        $notification = Notification::factory()->make([
            'user_id' => 'user-uuid-123',
            'type'    => 'demo_completed',
            'title'   => 'Demo Done',
            'message' => 'Your demo is complete.',
            'is_read' => false,
        ]);
        $payload = (new NotificationCreated($notification))->broadcastWith();

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('is_read', $payload);
        $this->assertArrayHasKey('created_at', $payload);
        $this->assertEquals('demo_completed', $payload['type']);
        $this->assertFalse($payload['is_read']);
    }

    #[Test]
    public function it_uses_custom_broadcast_event_name(): void
    {
        $notification = Notification::factory()->make(['user_id' => 'any']);
        $this->assertEquals('NotificationCreated', (new NotificationCreated($notification))->broadcastAs());
    }

    #[Test]
    public function it_uses_the_configured_broadcast_queue(): void
    {
        $notification = Notification::factory()->make(['user_id' => 'any']);
        $event = new NotificationCreated($notification);
        $this->assertEquals(config('coms.broadcast_queue', 'default'), $event->broadcastQueue());
    }
}
