<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class TripBooked implements ShouldBroadcast
{
    use SerializesModels;

    // Убираем afterCommit, чтобы события отправлялись синхронно
    // public $afterCommit = true;
    public function __construct(
        public Booking $booking,
        public int $passengerId,
        public int $driverId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->passengerId),
            new PrivateChannel('user.' . $this->driverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trip.booked';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id'    => $this->booking->trip_id,
            'booking_id' => $this->booking->id,
            'status'     => $this->booking->status,
            'seats'      => $this->booking->seats,
            'role'       => $this->booking->role, // driver / passenger
        ];
    }
}
