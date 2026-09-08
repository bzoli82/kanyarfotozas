<?php

use App\Models\EventSubscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_subscriptions', function (Blueprint $table) {
            $table->uuid('unsubscribe_token')->nullable()->after('country_id');
            $table->timestampTz('last_notified_at')->nullable()->after('unsubscribe_token');
        });

        EventSubscription::withoutEvents(function () {
            EventSubscription::query()->whereNull('unsubscribe_token')->get()->each(
                fn (EventSubscription $sub) => $sub->forceFill(['unsubscribe_token' => (string) Str::uuid()])->save()
            );
        });

        Schema::table('event_subscriptions', function (Blueprint $table) {
            $table->uuid('unsubscribe_token')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['unsubscribe_token', 'last_notified_at']);
        });
    }
};
