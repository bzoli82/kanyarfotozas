<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A `users.role` egy varchar + CHECK constraint (Laravel enum Postgresen).
        // Bővítjük az „organizer" (esemény-szervező) szerepkörrel.
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['superadmin','admin','photographer','organizer']::text[]))");

        DB::statement('ALTER TABLE invitations DROP CONSTRAINT IF EXISTS invitations_role_check');
        DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK (role::text = ANY (ARRAY['admin','photographer','organizer']::text[]))");

        Schema::table('events', function (Blueprint $table) {
            // Az esemény szervezője (pályanap-szervező) + a nettó bevételből járó részesedése.
            $table->foreignUuid('organizer_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('organizer_share_percent')->nullable()->after('organizer_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_id');
            $table->dropColumn('organizer_share_percent');
        });

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['superadmin','admin','photographer']::text[]))");

        DB::statement('ALTER TABLE invitations DROP CONSTRAINT IF EXISTS invitations_role_check');
        DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK (role::text = ANY (ARRAY['admin','photographer']::text[]))");
    }
};
