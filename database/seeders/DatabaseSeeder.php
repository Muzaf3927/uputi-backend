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
            'name' => 'User',
            'phone' => '123123123',
            'role' => 'driver',
            'password' => null,
            'balance' => 10000,
            'rating' => 5.0,
            'rating_count' => 25,
            'is_verified' => true,
        ]);
    }
}
