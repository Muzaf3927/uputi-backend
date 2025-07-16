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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // кому уведомление
            $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('set null'); // кто отправил (если нужно)
            $table->string('type'); // например: "chat"
            $table->text('message')->nullable(); // текст уведомления
            $table->json('data')->nullable(); // доп. данные: id сообщения, trip_id и т.п.
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
