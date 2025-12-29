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
        Schema::table('trips', function (Blueprint $table) {
            $table->string('from_address_normalized')->nullable()->after('from_address');
            $table->string('to_address_normalized')->nullable()->after('to_address');

            // индексы — очень важно для поиска
            $table->index('from_address_normalized');
            $table->index('to_address_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['from_address_normalized']);
            $table->dropIndex(['to_address_normalized']);

            $table->dropColumn([
                'from_address_normalized',
                'to_address_normalized',
            ]);
        });
    }

};
