<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // A HLS master playlist kulcsa a `public` diskon (hls/{id}/master.m3u8) — EPIC-16.
            $table->string('hls_playlist_s3_key')->nullable()->after('preview_sprite_interval');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('hls_playlist_s3_key');
        });
    }
};
