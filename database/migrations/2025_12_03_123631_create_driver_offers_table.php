<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_request_id')->constrained()->onDelete('cascade'); // запрос пассажира
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // водитель
            $table->string('carModel'); // модель машины
            $table->string('carColor'); // цвет машины
            $table->string('numberCar'); // номер машины
            $table->unsignedBigInteger('price')->nullable(); // цена, которую предлагает водитель
            $table->enum('status', ['pending', 'accepted', 'declined', 'cancelled'])->default('pending'); // статус оффера
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_offers');
    }
};
