<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A vásárló a pénztárban elfogadta az ÁSZF-et + az adatvédelmi tájékoztatót,
     * és tudomásul vette, hogy az azonnali digitális teljesítéssel megszűnik a
     * 14 napos elállási joga. Ennek időbélyege (bizonyíték).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('terms_accepted_at');
        });
    }
};
