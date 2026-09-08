<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // A nagy fajlok (original, download_jpeg, download_webp) egyutt koltoznek
            // a lokalis diskrol a NAS-ra egy batch jobban — ez azt jelzi, hol vannak most.
            $table->enum('original_storage', ['local', 'nas'])->default('local')->after('license_plate_confidence');
            $table->timestampTz('archived_at')->nullable()->after('original_storage');
            $table->unsignedTinyInteger('archive_attempts')->default(0)->after('archived_at');
            $table->text('archive_error')->nullable()->after('archive_attempts');

            $table->index('original_storage');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['original_storage', 'archived_at', 'archive_attempts', 'archive_error']);
        });
    }
};
