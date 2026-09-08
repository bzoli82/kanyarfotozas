<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Napló minden forensic-jelölt letöltésről — a jel maga önellenőrző
        // (HMAC), de ez a napló mutatja MIKOR és honnan (hashelt IP) töltötték le.
        Schema::create('download_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('format', 8);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_fingerprints');
    }
};
