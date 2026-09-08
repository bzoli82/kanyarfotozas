<?php

namespace App\Jobs;

use App\Mail\EventLiveNotificationMail;
use App\Models\Event;
use App\Models\EventSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * EPIC-17: egy esemeny `live`-ra valtasakor ertesiti a helyszinre feliratkozokat.
 * Helyszin-egyezes: azonos `location` (kis-nagybetu-fuggetlen) es — ha a
 * feliratkozasnak van orszaga — azonos `country_id`. Rate limit: max 100 e-mail/perc.
 */
class NotifyEventSubscribersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        $event = Event::query()->find($this->eventId);

        if (! $event || $event->status !== Event::STATUS_LIVE) {
            return;
        }

        $sent = 0;

        EventSubscription::query()
            ->whereRaw('LOWER(location) = ?', [mb_strtolower($event->location)])
            ->where(fn ($q) => $q->whereNull('country_id')->orWhere('country_id', $event->country_id))
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($event, &$sent) {
                foreach ($subscriptions as $subscription) {
                    Mail::to($subscription->email)->send(new EventLiveNotificationMail($event, $subscription));
                    $subscription->forceFill(['last_notified_at' => now()])->save();
                    $sent++;
                }

                if ($sent >= 100) {
                    sleep(60);
                    $sent = 0;
                }
            });
    }
}
