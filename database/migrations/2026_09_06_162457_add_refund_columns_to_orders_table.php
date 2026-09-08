<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('refunded_cents')->default(0)->after('discount_cents');
            // A szolgáltató saját rendelés-hivatkozása (SimplePay orderRef) — a visszatérítéshez kell.
            $table->string('payment_provider_order_ref')->nullable()->after('payment_provider_reference');
            $table->string('refund_reference')->nullable()->after('payment_provider_order_ref');
            $table->timestampTz('refunded_at')->nullable()->after('reminder_sent_at');
            $table->text('refund_reason')->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refunded_cents', 'refund_reference', 'refunded_at', 'refund_reason']);
        });
    }
};
