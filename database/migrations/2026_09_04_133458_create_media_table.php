<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('photographer_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['photo', 'video']);

            // S3 kulcsok
            $table->string('original_s3_key'); // privat
            $table->string('watermarked_s3_key')->nullable(); // publikus, WebP kep / 720p video
            $table->string('thumbnail_s3_key')->nullable(); // publikus, 400x300 WebP
            $table->string('download_jpeg_s3_key')->nullable(); // [foto] privat
            $table->string('download_webp_s3_key')->nullable(); // [foto] privat
            $table->string('preview_sprite_s3_key')->nullable(); // [video] publikus scrub sprite
            $table->unsignedSmallInteger('preview_sprite_interval')->nullable(); // [video] mp/frame
            $table->unsignedInteger('duration_seconds')->nullable(); // [video]

            $table->timestampTz('shot_at')->nullable(); // EXIF
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->enum('status', ['processing', 'ready', 'failed', 'hidden'])->default('processing');

            // OCR rendszamfelismeres (EPIC-13, de mezok mar EPIC-02-ben leteznek)
            $table->string('license_plate')->nullable();
            $table->json('license_plate_bbox')->nullable(); // {x,y,w,h}
            $table->boolean('license_plate_blurred')->default(false);
            $table->decimal('license_plate_confidence', 3, 2)->nullable();

            $table->timestamps();

            $table->index(['event_id', 'type', 'status']);
            $table->index('shot_at');
            $table->index('photographer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
