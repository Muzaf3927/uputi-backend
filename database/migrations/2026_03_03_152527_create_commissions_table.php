<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();

            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Финансовые данные
            $table->decimal('total_amount', 12, 2);       // общий оборот
            $table->decimal('commission_percent', 5, 2);  // 8.00
            $table->decimal('commission_amount', 12, 2);  // сумма комиссии

            // Тип комиссии
            $table->string('type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
