<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GDPR adatkiadási / törlési kérelmek. A látogató az e-mail címére kapott
 * hivatkozással igazolja a kérést; a superadmin dolgozza fel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->enum('type', ['export', 'delete']);
            $table->enum('status', ['pending', 'verified', 'completed', 'rejected'])->default('pending');
            $table->uuid('token')->unique();
            $table->string('note')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
    }
};
