<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('key', [
                'hero', 'how_it_works', 'latest_events', 'video_showcase', 'stats',
                'payment_methods', 'photographers', 'map', 'reviews', 'faq_mini',
                'social', 'footer',
            ])->unique();
            $table->string('label');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_locked')->default(false); // hero, latest_events, footer
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_sections');
    }
};
