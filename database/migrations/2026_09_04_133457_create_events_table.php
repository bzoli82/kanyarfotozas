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

        // GPS sugaras kereses (EPIC-10, App\Services\EventSearch): ha az adatbazison
        // elerheto a PostGIS, terbeli GIST indexet teszunk a lat/lon parosra
        // (ST_DWithin/ST_Distance). PostGIS nelkuli hostingon (a funkcio alapbol KI,
        // ld. App\Services\GeoSearchSettings) sima b-tree index keszul, es a migracio
        // igy is lefut. PostGIS kesobbi telepitese utan a GIST index kezzel felvehető:
        //   CREATE INDEX events_geog_idx ON events USING GIST (ST_SetSRID(ST_MakePoint(longitude, latitude), 4326));
        $hasPostgis = (bool) DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'postgis'");

        if ($hasPostgis) {
            DB::statement('CREATE INDEX events_geog_idx ON events USING GIST (ST_SetSRID(ST_MakePoint(longitude, latitude), 4326))');
        } else {
            DB::statement('CREATE INDEX events_geog_idx ON events (latitude, longitude)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
