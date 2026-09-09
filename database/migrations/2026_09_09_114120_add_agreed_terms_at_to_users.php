<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A csapattag (fotós) elfogadta a Fotós Megállapodást (benne az oldalon kívüli
 * értékesítést tiltó záradékkal). Meghíváskor kötelező; a régi fotósoknak
 * a dashboardon egy egyszeri elfogadó gomb.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('agreed_terms_at')->nullable()->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('agreed_terms_at');
        });
    }
};
