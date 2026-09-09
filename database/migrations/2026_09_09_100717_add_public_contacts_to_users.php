<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fotós nyilvános elérhetőségei a „Fotósok" oldalhoz: egy céges e-mail-cím
 * (a bejelentkezési e-mailtől külön, ami lehet privát), a saját weboldal és a
 * TikTok-oldal (az Instagram / Facebook / YouTube mezők már megvannak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('public_email')->nullable()->after('email');
            $table->string('website')->nullable()->after('public_email');
            $table->string('social_tiktok')->nullable()->after('social_youtube');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['public_email', 'website', 'social_tiktok']);
        });
    }
};
