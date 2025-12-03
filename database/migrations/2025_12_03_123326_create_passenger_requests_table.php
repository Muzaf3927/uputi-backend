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
        Schema::create('passenger_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // пассажир
            
            // Начальная точка - координаты или название адреса
            $table->decimal('from_lat', 10, 8)->nullable(); // широта отправления
            $table->decimal('from_lng', 11, 8)->nullable(); // долгота отправления
            $table->string('from_address')->nullable(); // название начального адреса
            
            // Конечная точка - координаты или название адреса
            $table->decimal('to_lat', 10, 8)->nullable(); // широта назначения
            $table->decimal('to_lng', 11, 8)->nullable(); // долгота назначения
            $table->string('to_address')->nullable(); // название конечного адреса
            
            // Дата и время поездки
            $table->date('date');
            $table->time('time');
            
            // Сумма и количество пассажиров
            $table->unsignedInteger('amount')->nullable(); // сумма
            $table->unsignedTinyInteger('seats')->default(1); // количество пассажиров
            
            // Комментарий
            $table->text('comment')->nullable();
            
            // Статус заявки
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            
            $table->timestamps();
            
            // Индексы для быстрого поиска
            $table->index('user_id');
            $table->index('status');
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passenger_requests');
    }
};
