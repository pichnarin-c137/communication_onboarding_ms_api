<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnomalyDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $trainerId,
        public readonly string $type,
        public readonly string $severity,
        public readonly array $details,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('trainers')];
    }

    public function broadcastWith(): array
    {
        return [
            'trainer_id' => $this->trainerId,
            'type' => $this->type,
            'severity' => $this->severity,
            'details' => $this->details,
        ];
    }

    public function broadcastAs(): string
    {
        return 'anomaly.detected';
    }

    public function broadcastQueue(): string
    {
        return config('coms.broadcast_queue', 'default');
    }
}
