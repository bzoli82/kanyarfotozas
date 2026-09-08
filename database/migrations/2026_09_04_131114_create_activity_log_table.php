<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');

            // A subject vegyes tipusu lehet (Event/Media/Order = bigint id, User = UUID id),
            // ezert sima string oszlopkent taroljuk mindket kulcstipust, morphs() helyett.
            $table->string('subject_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->index(['subject_id', 'subject_type'], 'subject');

            $table->string('event')->nullable();

            // causer mindig a Users tabla (UUID primary key)
            $table->string('causer_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->index(['causer_id', 'causer_type'], 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }
};
