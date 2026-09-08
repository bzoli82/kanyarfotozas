<?php

namespace App\Observers;

use App\Jobs\NotifyEventSubscribersJob;
use App\Models\Event;

class EventObserver
{
    /**
     * Ha egy esemeny `announced`/`draft`-bol `live`-ra valt (a kepek elerhetok
     * lettek), ertesitjuk a helyszinre feliratkozokat — EPIC-17.
     */
    public function updated(Event $event): void
    {
        if (! $event->wasChanged('status')) {
            return;
        }

        $previous = $event->getOriginal('status');

        if ($event->status === Event::STATUS_LIVE && in_array($previous, [Event::STATUS_ANNOUNCED, Event::STATUS_DRAFT], true)) {
            NotifyEventSubscribersJob::dispatch($event->id);
        }
    }
}
