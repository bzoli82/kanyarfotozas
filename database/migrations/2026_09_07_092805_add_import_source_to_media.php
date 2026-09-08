<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Ha a mediat az FTP/NAS kezi importbol hoztuk be (Admin\MediaImportController),
            // ide kerul a tavoli forras-utvonal — igy az ismetelt import kiszurheto.
            $table->string('import_source_path')->nullable()->after('original_storage');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('import_source_path');
        });
    }
};
