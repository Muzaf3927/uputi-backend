<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
        ]);

        User::create([
            'name' => 'Passenger',
            'phone' => '123123123',
            'password' => Hash::make('123321'),
            'balance' => 10000,
            'rating' => 5.0,
            'rating_count' => 25,
        ]);

        User::create([
            'name' => 'Driver',
            'phone' => '123123122',
            'password' => Hash::make('123321'),
            'balance' => 10000,
            'rating' => 5.0,
            'rating_count' => 25,
        ]);
    }
}
