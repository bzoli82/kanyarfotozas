<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kézbesítési gyorsítótár (delivery cache) állapota rendelésenként — a
     * megvásárolt fájlok másolása az archív rétegről a gyors `delivery` diskre.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_status')->default('pending'); // pending | ready | failed
            $table->timestamp('fulfillment_prepared_at')->nullable();
            $table->unsignedTinyInteger('fulfillment_attempts')->default(0);
            $table->string('fulfillment_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_status', 'fulfillment_prepared_at', 'fulfillment_attempts', 'fulfillment_error']);
        });
    }
};
