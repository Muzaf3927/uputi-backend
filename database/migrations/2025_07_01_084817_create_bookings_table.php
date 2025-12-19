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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('seats')->default(1); // сколько мест забронировано
            $table->unsignedInteger('offered_price')->nullable(); // цена, предложенная
            $table->text('comment')->nullable(); // комментарий к заявке
            $table->string('role')->nullable(); // passenger | driver
            $table->enum('status', ['requested', 'in_progress', 'completed', 'cancelled'])->default('in_progress');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
