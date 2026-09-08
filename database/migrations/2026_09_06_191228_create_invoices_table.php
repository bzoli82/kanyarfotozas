<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiállított számlák (és sztornók). A számlát külső szolgáltató (Billingo)
 * állítja ki és jelenti a NAV Online Számlának; itt a hivatkozást + a PDF-et tároljuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);                       // billingo | manual
            $table->enum('type', ['normal', 'storno'])->default('normal');
            $table->foreignId('storno_of')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('external_id')->nullable();            // a szolgáltatónál a dokumentum id
            $table->string('number')->nullable();                 // a számla sorszáma
            $table->unsignedInteger('gross_cents')->default(0);
            $table->string('pdf_path')->nullable();               // a `local` (privát) diskon
            $table->enum('status', ['issued', 'failed'])->default('failed');
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('issued_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
