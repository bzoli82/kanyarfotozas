<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('ip_hash'); // az IP cim maga nem tarolodik nyersen, csak hashelve (GDPR)
            $table->timestampTz('created_at');

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_login_attempts');
    }
};
