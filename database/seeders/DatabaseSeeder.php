<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
        ]);

        User::create([
            'name' => '3Passenger',
            'phone' => '123123123',
            'password' => null,
            'balance' => 10000,
            'rating' => 5.0,
            'rating_count' => 25,
        ]);

        User::create([
            'name' => '2Driver',
            'phone' => '123123122',
            'password' => null,
            'balance' => 10000,
            'rating' => 5.0,
            'rating_count' => 25,
        ]);


    }
}
