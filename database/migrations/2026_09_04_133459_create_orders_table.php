<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('buyer_email');
            $table->unsignedInteger('total_cents');
            $table->enum('payment_provider', ['stripe', 'simplepay', 'barion'])->nullable();
            $table->string('payment_provider_reference')->nullable(); // webhook idempotencia
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');

            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->unsignedInteger('discount_cents')->default(0);

            // Letoltesi token: UUID, 72 ora lejarat, max 5x hasznalhato
            $table->uuid('download_token')->nullable()->unique();
            $table->timestampTz('token_expires_at')->nullable();
            $table->unsignedTinyInteger('download_token_uses')->default(0);

            $table->timestamps();

            $table->index('buyer_email');
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
