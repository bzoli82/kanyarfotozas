<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographer_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('photographer_id')->constrained('users')->cascadeOnDelete();
            $table->string('payout_number', 40)->nullable()->unique(); // az id-ből képződik a created hookban
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedBigInteger('gross_cents')->default(0);   // a bevonat eladasok bruttoja
            $table->unsignedBigInteger('amount_cents')->default(0);  // a fotosnak fizetendo osszeg
            $table->unsignedInteger('media_count')->default(0);
            $table->enum('status', ['draft', 'paid'])->default('draft');
            $table->string('method', 40)->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['photographer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photographer_payouts');
    }
};
