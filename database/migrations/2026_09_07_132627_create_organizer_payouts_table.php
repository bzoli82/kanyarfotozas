<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A szervezőnek kifizetett részesedés rögzítése (egyszerű, kézi könyvelés —
        // nincs bizonylat-előkészítés/draft folyamat, mint a fotós-kifizetésnél).
        Schema::create('organizer_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organizer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_payouts');
    }
};
