<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainerEtaUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $trainerId,
        public readonly float $etaMinutes,
        public readonly int $distanceMeters,
        public readonly ?array $routeGeometry,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('trainers')];
    }

    public function broadcastWith(): array
    {
        return [
            'trainer_id' => $this->trainerId,
            'eta_minutes' => $this->etaMinutes,
            'distance_meters' => $this->distanceMeters,
            'route_geometry' => $this->routeGeometry,
        ];
    }

    public function broadcastAs(): string
    {
        return 'trainer.eta.updated';
    }

    public function broadcastQueue(): string
    {
        return config('coms.broadcast_queue', 'default');
    }
}
