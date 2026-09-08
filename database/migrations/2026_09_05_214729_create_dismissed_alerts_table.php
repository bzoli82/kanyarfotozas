<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dismissed_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_key')->unique(); // stabil kulcs a szabaly + targy alapjan
            $table->foreignUuid('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('dismissed_until'); // eddig rejtve; utana ujra megjelenik, ha meg fennall
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dismissed_alerts');
    }
};
