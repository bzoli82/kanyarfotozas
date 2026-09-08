<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Esemeny-szintu arazas: egy ar minden fotora, egy minden videora.
        // NULL => a `base_price_huf` beallitas (App\Models\Event::priceFor()).
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('photo_price_cents')->nullable()->after('status');
            $table->unsignedInteger('video_price_cents')->nullable()->after('photo_price_cents');
        });

        // Duplikatum-szures: a feltoltott/importalt fajl tartalmanak SHA-256 lenyomata.
        // Eventenkent egyedi => ugyanaz a fajl nem kerul be ketszer ugyanabba a galeriaba.
        Schema::table('media', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('original_s3_key');
            $table->index(['event_id', 'content_hash']);
        });

        // Meglevo adatok konzisztensse tetele (demo): az esemenyek megkapjak az
        // alap arat, a meglevo mediak ara ehhez igazodik.
        $base = (int) (SiteSetting::get('base_price_huf', 1490) ?: 1490);

        DB::table('events')->update([
            'photo_price_cents' => $base,
            'video_price_cents' => $base + 1000,
        ]);

        DB::statement('UPDATE media SET price_cents = e.photo_price_cents FROM events e WHERE media.event_id = e.id AND media.type = ?', ['photo']);
        DB::statement('UPDATE media SET price_cents = e.video_price_cents FROM events e WHERE media.event_id = e.id AND media.type = ?', ['video']);
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'content_hash']);
            $table->dropColumn('content_hash');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['photo_price_cents', 'video_price_cents']);
        });
    }
};
