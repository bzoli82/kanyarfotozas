<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->string('type', 16)->default('image')->after('id');
            $table->string('image_path')->nullable()->change();
            $table->string('video_path')->nullable()->after('image_path');
            $table->string('poster_path')->nullable()->after('video_path');
            $table->unsignedSmallInteger('duration_seconds')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn(['type', 'video_path', 'poster_path', 'duration_seconds']);
        });
    }
};
