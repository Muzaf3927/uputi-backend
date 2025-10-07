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
            'name' => 'Test User',
            'phone' => '900000000',
            'role' => 'driver',
            'password' => Hash::make('900000000'),
            'balance' => 10000,
            'rating' => 5.0,
            'rating_count' => 25,
            'is_verified' => true,
        ]);
    }
}
