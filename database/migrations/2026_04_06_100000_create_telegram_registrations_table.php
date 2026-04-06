<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->unique();
            $table->string('phone')->nullable();
            $table->string('name')->nullable();
            $table->string('step')->default('start');
            $table->timestamps();
        });

        // Тестовые аккаунты
        User::updateOrCreate(
            ['phone' => '123456789'],
            ['name' => 'Passenger', 'password' => Hash::make('123321'), 'balance' => 10000, 'rating' => 5.0, 'rating_count' => 25]
        );

        User::updateOrCreate(
            ['phone' => '987654321'],
            ['name' => 'Driver', 'password' => Hash::make('123321'), 'balance' => 10000, 'rating' => 5.0, 'rating_count' => 25]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_registrations');
    }
};
