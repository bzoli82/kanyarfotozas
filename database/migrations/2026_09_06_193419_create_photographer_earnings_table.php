<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographer_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('photographer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('order_media_id')->unique()->constrained('order_media')->cascadeOnDelete();
            $table->unsignedInteger('gross_cents');            // order_media.price_cents a vasarlas pillanataban
            $table->unsignedSmallInteger('share_percent');     // a fotos jutalek %-a az elszamolas rogzitesekor
            $table->unsignedInteger('amount_cents');           // round(gross * share / 100)
            $table->enum('status', ['pending', 'paid', 'reversed'])->default('pending');
            $table->foreignId('payout_id')->nullable()->constrained('photographer_payouts')->nullOnDelete();
            $table->timestampTz('earned_at');
            $table->timestampTz('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['photographer_id', 'status']);
        });

        // Visszamenoleges feltoltes: minden mar kifizetett rendeles fotos-jutaleka
        // "pending" tetelkent bekerul, hogy az elso elszamolaskor is meglegyen.
        DB::statement(<<<'SQL'
            INSERT INTO photographer_earnings
                (photographer_id, order_id, media_id, order_media_id, gross_cents, share_percent, amount_cents, status, earned_at, created_at, updated_at)
            SELECT
                m.photographer_id,
                o.id,
                m.id,
                om.id,
                om.price_cents,
                COALESCE(u.revenue_share_percent, 0),
                CAST(ROUND(om.price_cents * COALESCE(u.revenue_share_percent, 0) / 100.0) AS integer),
                'pending',
                o.updated_at,
                now(),
                now()
            FROM order_media om
            JOIN orders o ON o.id = om.order_id
            JOIN media m ON m.id = om.media_id
            JOIN users u ON u.id = m.photographer_id
            WHERE o.payment_status = 'paid'
              AND m.photographer_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('photographer_earnings');
    }
};
