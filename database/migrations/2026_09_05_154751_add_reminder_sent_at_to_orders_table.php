<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // A letoltesi emlekezteto e-mail elkuldesenek idopontja — hogy egy
            // rendeleshez csak egyszer menjen ki (SendDownloadReminders parancs).
            $table->timestampTz('reminder_sent_at')->nullable()->after('download_token_uses');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
