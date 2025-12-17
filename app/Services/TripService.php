<?php


namespace App\Services;

use App\Models\Trip;
use App\Models\User;

class TripService
{
    /**
     * Получить поездки пользователя по статусу
     */
    public function getMyTripsByStatus(User $user, string $status)
    {
        return Trip::where('user_id', $user->id)
            ->where('status', $status)
            ->with($this->relationsForUser($user))
            ->latest()
            ->get();
    }


    /**
     * Определяем, какие связи грузить
     * 👇 КЛЮЧЕВОЕ МЕСТО
     */
    private function relationsForUser(User $user): array
    {
        // если я пассажир — мне важны водители и их машины
        if ($user->role === 'passenger') {
            return [
                'bookings' => function ($q) {
                    $q->where('status', 'in_progress')
                        ->with([
                            'user.car' // 👈 водитель + его машина
                        ]);
                }
            ];
        }

        // если я водитель — машины не нужны
        return [
            'bookings' => function ($q) {
                $q->where('status', 'in_progress')
                    ->with('user'); // пассажир
            }
        ];
    }

}
