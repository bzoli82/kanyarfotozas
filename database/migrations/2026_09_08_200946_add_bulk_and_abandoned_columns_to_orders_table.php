<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Automatikus mennyiségi kedvezmény (App\Services\BulkDiscount) — a
            // `discount_cents` marad a kuponé, ez a kettő külön mező.
            $table->unsignedInteger('bulk_discount_cents')->default(0)->after('discount_cents');
            // Elhagyott kosár emlékeztető: egyszer megy ki (App\Console\Commands\SendAbandonedCartReminders).
            $table->timestampTz('abandoned_reminder_sent_at')->nullable()->after('reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['bulk_discount_cents', 'abandoned_reminder_sent_at']);
        });
    }
};
