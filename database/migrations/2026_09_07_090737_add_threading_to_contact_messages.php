<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            // Ha az uzenet egy konkret feltolto fotoshoz szol (a media-oldali
            // „Kerdes a fotoshoz" urlaprol), akkor ide kerul a fotos user id-ja.
            $table->foreignUuid('photographer_id')->nullable()->after('contact_type')->constrained('users')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->after('photographer_id')->constrained()->nullOnDelete();
            $table->timestamp('last_reply_at')->nullable()->after('status');
        });

        Schema::create('contact_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_message_id')->constrained()->cascadeOnDelete();
            // A valasz szerzoje (admin vagy fotos). Null csak akkor, ha a szerzo user torlodott.
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            // Ha az admin egy fotos NEVEBEN valaszolt (mert a fotos nem elerheto).
            $table->foreignUuid('on_behalf_of_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('emailed')->default(true);
            $table->timestamps();

            $table->index('contact_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_replies');

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photographer_id');
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn('last_reply_at');
        });
    }
};
