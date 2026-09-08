<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_shares', function (Blueprint $table) {
            $table->id();
            $table->string('share_token', 12)->unique(); // rovid, olvashato kod
            $table->json('media_ids');
            $table->timestampTz('expires_at');
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_shares');
    }
};
