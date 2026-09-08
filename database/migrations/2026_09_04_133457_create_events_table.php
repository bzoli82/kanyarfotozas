<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
            $table->string('name');
            $table->string('location');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->date('event_date');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->string('slug')->unique();

            // draft: csak admin latja | announced: 'Hamarosan' + feliratkozas | live: galeria elerheto | archived: csokkentett ar
            $table->enum('status', ['draft', 'announced', 'live', 'archived'])->default('draft');
            $table->timestampTz('featured_until')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('starts_at');
            $table->index(['country_id', 'status']);
        });

        // PostGIS spatial index a lat/lon parosra (GPS sugaras kereses, EPIC-10 ST_DWithin)
        DB::statement('CREATE INDEX events_geog_idx ON events USING GIST (ST_SetSRID(ST_MakePoint(longitude, latitude), 4326))');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
