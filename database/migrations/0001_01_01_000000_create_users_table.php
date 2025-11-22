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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('avatar')->nullable();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('role')->nullable();
            $table->string('password')->nullable();
            $table->integer('balance')->default(0);
            $table->decimal('rating', 2, 1)->default(5.0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->string('verification_code')->nullable();
            $table->string('telegram_chat_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
