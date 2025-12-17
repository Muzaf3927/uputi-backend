<?php


namespace App\Services;

use App\Models\Booking;
use App\Models\User;

class BookingService
{
    /**
     * Мои брони по статусу
     */
    public function getMyBookingsByStatus(User $user, string $status)
    {
        return Booking::where('user_id', $user->id)
            ->where('status', $status)
            ->with($this->relationsForUser($user))
            ->latest()
            ->get();
    }

    /**
     * Определяем связи в зависимости от роли
     */
    private function relationsForUser(User $user): array
    {
        // 👤 если я пассажир → нужна машина водителя
        if ($user->role === 'passenger') {
            return [
                'trip' => function ($q) {
                    $q->with([
                        'user.car' // 👈 водитель + его машина
                    ]);
                }
            ];
        }

        // 🚗 если я водитель → просто инфо о заказе
        return [
            'trip.user' // пассажир
        ];
    }
}
