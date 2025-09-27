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
        User::create([
            'name' => 'Muzaf',
            'phone' => '900012314',
            'role' => 'driver',
            'password' => Hash::make('900012314'),
            'balance' => 10000,
            'rating' => 5.0,
            'rating_count' => 25,
            'is_verified' => true,
        ]);
    }
}
