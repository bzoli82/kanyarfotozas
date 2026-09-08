<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Számlázási adatok a rendeléshez. Magyar webshopnál az online kártyás eladásról
 * számla kötelező — ehhez legalább a vevő NEVE + ORSZÁGA kell (egyszerűsített
 * számlához magánszemélynél a cím elhagyható). Cégnél az adószám adja a céges számlát.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_name')->nullable()->after('buyer_email');
            $table->string('billing_country', 2)->nullable()->after('billing_name');
            $table->string('billing_zip', 20)->nullable()->after('billing_country');
            $table->string('billing_city')->nullable()->after('billing_zip');
            $table->string('billing_address')->nullable()->after('billing_city');
            $table->string('billing_tax_number', 30)->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['billing_name', 'billing_country', 'billing_zip', 'billing_city', 'billing_address', 'billing_tax_number']);
        });
    }
};
