<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainerLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $trainerId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $speed,
        public readonly ?float $accuracy,
        public readonly string $timestamp,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('trainers')];
    }

    public function broadcastWith(): array
    {
        return [
            'trainer_id' => $this->trainerId,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'speed' => $this->speed,
            'accuracy' => $this->accuracy,
            'timestamp' => $this->timestamp,
        ];
    }

    public function broadcastAs(): string
    {
        return 'trainer.location.updated';
    }

    public function broadcastQueue(): string
    {
        return config('coms.broadcast_queue', 'default');
    }
}
