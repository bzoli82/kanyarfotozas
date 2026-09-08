<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->unsignedInteger('price_cents'); // ar a vasarlas pillanataban (megvasarolt ar rogzitese)
            $table->timestamps();

            $table->unique(['order_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_media');
    }
};
