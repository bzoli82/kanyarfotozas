<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('location');
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['location', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_subscriptions');
    }
};
