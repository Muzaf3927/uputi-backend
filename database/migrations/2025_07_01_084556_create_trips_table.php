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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // водитель
            $table->string('from_city');
            $table->string('to_city');
            $table->date('date');
            $table->time('time');
            $table->unsignedTinyInteger('seats'); // доступные места
            $table->decimal('price', 8, 2); // цена за место
            $table->text('note')->nullable(); // комментарий
            $table->string('carModel')->nullable();
            $table->string('carColor')->nullable();
            $table->string('numberCar')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
