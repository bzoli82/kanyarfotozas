<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email_hash', 64)->index(); // sha256(lower(email))
            $table->string('otp_hash', 64);            // sha256(otp)
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_otps');
    }
};
