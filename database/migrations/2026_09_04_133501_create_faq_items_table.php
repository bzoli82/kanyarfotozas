<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_items', function (Blueprint $table) {
            $table->id();
            $table->string('question_hu');
            $table->text('answer_hu');
            $table->string('question_en')->nullable();
            $table->text('answer_en')->nullable();
            $table->string('category')->default('general'); // pl. general | payment | download | video
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_homepage')->default(false); // GYIK mini szekcio a fooldalon
            $table->timestamps();

            $table->index(['active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
    }
};
