<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainerStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $trainerId,
        public readonly string $status,
        public readonly ?string $customerId,
        public readonly ?string $customerName,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly string $detectionMethod,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('trainers')];
    }

    public function broadcastWith(): array
    {
        return [
            'trainer_id' => $this->trainerId,
            'status' => $this->status,
            'customer_id' => $this->customerId,
            'customer_name' => $this->customerName,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'detection_method' => $this->detectionMethod,
        ];
    }

    public function broadcastAs(): string
    {
        return 'trainer.status.changed';
    }

    public function broadcastQueue(): string
    {
        return config('coms.broadcast_queue', 'default');
    }
}
