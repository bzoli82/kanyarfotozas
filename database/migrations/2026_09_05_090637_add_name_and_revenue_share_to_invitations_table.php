<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // Az eredeti EPIC-02 sema nem tartalmazta ezeket, pedig a meghivo urlapnak
            // (master.txt 9.3) mar meghiváskor fel kell vennie a leendo felhasznalo
            // nevet es jutalek-szazalekat, hogy elfogadaskor a User rekord keszen legyen.
            $table->string('name')->after('email');
            $table->unsignedTinyInteger('revenue_share_percent')->default(70)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['name', 'revenue_share_percent']);
        });
    }
};
