<?php

use App\Models\Order;
use App\Services\SiteBranding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ember-olvasható rendelésazonosító (sorszám): `{PREFIX}-{ÉV}-{6 jegy}`, pl.
 * KAN-2026-000042. A rendelés `id`-jából + a létrehozás évéből képződik — így
 * ütközésmentes és monoton. Az admin ez alapján is visszakeres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 40)->nullable()->unique()->after('id');
        });

        $prefix = $this->prefix();

        Order::query()->whereNull('order_number')->orderBy('id')->chunkById(200, function ($orders) use ($prefix) {
            foreach ($orders as $order) {
                $order->newQuery()->whereKey($order->id)->update([
                    'order_number' => sprintf('%s-%d-%06d', $prefix, $order->created_at?->year ?? now()->year, $order->id),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });
    }

    private function prefix(): string
    {
        try {
            $slug = app(SiteBranding::class)->slug();
        } catch (Throwable) {
            $slug = 'ord';
        }

        return Str::upper(Str::substr(preg_replace('/[^a-z0-9]/i', '', $slug) ?: 'ORD', 0, 3));
    }
};
