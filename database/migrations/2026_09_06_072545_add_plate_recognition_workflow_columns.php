<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // pending = még nem futott / a felismerés ki volt kapcsolva
            // none = lefutott, nincs rendszám a képen
            // detected = megbízható találat (>= küszöb)
            // unidentifiable = találat, de a küszöb alatt (kézi ellenőrzés)
            $table->string('license_plate_status', 20)->default('pending')->after('license_plate_confidence');
        });

        Schema::table('orders', function (Blueprint $table) {
            // A vásárló hozzájárult, hogy a homályosított rendszámú felvételt
            // eredeti (homályosítás nélküli) formában kapja meg — EPIC-13 AC.
            $table->boolean('plate_consent')->default(false)->after('discount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('license_plate_status');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('plate_consent');
        });
    }
};
