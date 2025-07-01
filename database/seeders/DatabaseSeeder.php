<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Trip;
use App\Models\Booking;
use App\Models\Wallet;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Создаём 5 пользователей
        $users = User::factory()->count(5)->create();

        foreach ($users as $user) {
            // Добавляем каждому кошелёк
            Wallet::create([
                'user_id' => $user->id,
                'balance' => rand(1000, 5000)
            ]);
        }

        // Создаём поездки от первых 2 пользователей
        $users->take(2)->each(function ($user) {
            Trip::create([
                'user_id' => $user->id,
                'from_city' => 'Москва',
                'to_city' => 'Санкт-Петербург',
                'date' => now()->addDays(2),
                'time' => '10:00',
                'seats' => 3,
                'price' => 1500,
                'note' => 'Комфортная поездка',
                'status' => 'active'
            ]);
        });

        // Один пассажир бронирует поездку
        $trip = Trip::first();
        Booking::create([
            'trip_id' => $trip->id,
            'user_id' => $users->last()->id,
            'seats' => 1,
            'status' => 'confirmed'
        ]);
    }
}
