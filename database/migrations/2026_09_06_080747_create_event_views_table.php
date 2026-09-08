<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Napi galeria-megtekintes rollup — a dashboard konverzios tolcser (EPIC-18)
 * valodi top-of-funnel lepcsoje kulso analitika (Plausible) nelkul. Esemenyenkent
 * es naponta egy sor, atomi increment-tel noveljuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('viewed_on');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'viewed_on']);
            $table->index('viewed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_views');
    }
};
