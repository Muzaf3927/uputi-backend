<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class TripUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public Trip $trip,
        public array $notifyUserIds
    ) {}

    /**
     * Уведомляем ТОЛЬКО нужных пользователей
     */
    public function broadcastOn(): array
    {
        return collect($this->notifyUserIds)
            ->unique()
            ->map(fn ($id) => new PrivateChannel('user.' . $id))
            ->toArray();
    }

    public function broadcastAs(): string
    {
        return 'trip.updated';
    }

    /**
     * Минимальный, но достаточный payload
     */
    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'status'  => $this->trip->status,
            'seats'   => $this->trip->seats,
        ];
    }
}
