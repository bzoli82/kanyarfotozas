<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_events', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique(); // exception class + fájl + sor hash
            $table->string('exception_class');
            $table->text('message');
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->index('resolved_at');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_events');
    }
};
