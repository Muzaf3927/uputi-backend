<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Trip;
use App\Models\Booking;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Создаём 2 пользователей
        $users = collect();

        // Первый пользователь
        $user1 = User::create([
            'name' => 'Алексей Иванов',
            'phone' => '+79001234567',
            'role' => 'driver',
            'password' => Hash::make('password123'),
            'balance' => 5000,
            'rating' => 4.8,
            'rating_count' => 15,
            'is_verified' => true,
        ]);
        $users->push($user1);

        // Второй пользователь
        $user2 = User::create([
            'name' => 'Мария Петрова',
            'phone' => '+79007654321',
            'role' => 'driver',
            'password' => Hash::make('password123'),
            'balance' => 3500,
            'rating' => 4.6,
            'rating_count' => 12,
            'is_verified' => true,
        ]);
        $users->push($user2);

        // Создаём специального пользователя для тестирования
        $specialUser = User::create([
            'name' => 'Muzaffar Admin',
            'phone' => '900038902',
            'role' => 'driver',
            'password' => Hash::make('39273927'),
            'balance' => 10000,
            'rating' => 5.0,
            'rating_count' => 25,
            'is_verified' => true,
        ]);
        $users->push($specialUser);

        // Создаём кошельки для пользователей
        foreach ($users as $user) {
            Wallet::create([
                'user_id' => $user->id,
                'balance' => $user->balance
            ]);
        }

        // Создаём access token для специального пользователя
        $specialUser->tokens()->delete(); // Удаляем старые токены
        $accessToken = $specialUser->createToken('test_token')->plainTextToken;

        // Выводим токен в консоль для удобства
        echo "\n=== СПЕЦИАЛЬНЫЙ ПОЛЬЗОВАТЕЛЬ ===\n";
        echo "Телефон: 900038902\n";
        echo "Пароль: 39273927\n";
        echo "Access Token: " . $accessToken . "\n";
        echo "===============================\n\n";

        // Создаём по 3 поездки для каждого пользователя
        $cities = [
            ['from' => 'Москва', 'to' => 'Санкт-Петербург'],
            ['from' => 'Москва', 'to' => 'Казань'],
            ['from' => 'Санкт-Петербург', 'to' => 'Москва'],
            ['from' => 'Казань', 'to' => 'Москва'],
            ['from' => 'Москва', 'to' => 'Нижний Новгород'],
            ['from' => 'Санкт-Петербург', 'to' => 'Казань']
        ];

        $carModels = ['Toyota Camry', 'Honda Accord', 'BMW 3 Series', 'Mercedes C-Class', 'Audi A4', 'Volkswagen Passat'];
        $carColors = ['Белый', 'Чёрный', 'Серый', 'Синий', 'Красный', 'Серебристый'];
        $carNumbers = ['А123БВ777', 'В456ГД123', 'С789ЕЖ456', 'Д012ЗИ789', 'Е345КЛ012', 'Ф678МН345'];

        foreach ($users as $userIndex => $user) {
            for ($i = 0; $i < 3; $i++) {
                $cityIndex = ($userIndex * 3 + $i) % count($cities);
                $carIndex = ($userIndex * 3 + $i) % count($carModels);

                Trip::create([
                    'user_id' => $user->id,
                    'from_city' => $cities[$cityIndex]['from'],
                    'to_city' => $cities[$cityIndex]['to'],
                    'date' => now()->addDays(rand(1, 7)),
                    'time' => sprintf('%02d:%02d', rand(6, 22), rand(0, 59)),
                    'seats' => rand(2, 4),
                    'price' => rand(800, 2500),
                    'note' => 'Комфортная поездка, кондиционер, Wi-Fi',
                    'carModel' => $carModels[$carIndex],
                    'carColor' => $carColors[$carIndex],
                    'numberCar' => $carNumbers[$carIndex],
                    'status' => 'active'
                ]);
            }
        }

        // Создаём дополнительные поездки для специального пользователя
        $specialTrips = [
            ['from' => 'Москва', 'to' => 'Сочи'],
            ['from' => 'Санкт-Петербург', 'to' => 'Москва'],
            ['from' => 'Москва', 'to' => 'Екатеринбург']
        ];

        foreach ($specialTrips as $index => $trip) {
            Trip::create([
                'user_id' => $specialUser->id,
                'from_city' => $trip['from'],
                'to_city' => $trip['to'],
                'date' => now()->addDays(rand(1, 14)),
                'time' => sprintf('%02d:%02d', rand(8, 20), rand(0, 59)),
                'seats' => rand(3, 5),
                'price' => rand(1200, 3000),
                'note' => 'Премиум поездка, кондиционер, Wi-Fi, закуски',
                'carModel' => 'Mercedes S-Class',
                'carColor' => 'Чёрный',
                'numberCar' => 'А999АА777',
                'status' => 'active'
            ]);
        }
    }
}
