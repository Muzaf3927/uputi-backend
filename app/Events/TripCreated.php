<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

use Illuminate\Queue\SerializesModels;

class TripCreated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public Trip $trip)
    {
    }

    public function broadcastOn(): Channel
    {
        // публичный канал для всех водителей (карта)
        return new Channel('drivers.trips');
    }

    public function broadcastAs(): string
    {
        return 'trip.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->trip->id,
            'user_id'      => $this->trip->user_id,
            'role'         => $this->trip->role, // driver или passenger
            'status'       => $this->trip->status,
            'from_lat'     => $this->trip->from_lat,
            'from_lng'     => $this->trip->from_lng,
            'from_address' => $this->trip->from_address,
            'to_lat'       => $this->trip->to_lat,
            'to_lng'       => $this->trip->to_lng,
            'to_address'   => $this->trip->to_address,
            'date'         => $this->trip->date,
            'time'         => $this->trip->time,
            'amount'       => $this->trip->amount,
            'seats'        => $this->trip->seats,
        ];
    }
}
