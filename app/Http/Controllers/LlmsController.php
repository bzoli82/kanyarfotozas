<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FaqItem;
use App\Services\GeoSettings;
use App\Services\SeoSettings;
use App\Services\SiteBranding;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Dinamikus `/llms.txt` — AI-crawlereknek szánt, ember által olvasható
 * oldaltérkép + leírás (Markdown, az llms.txt konvenció szerint).
 * Ld. App\Services\GeoSettings.
 */
class LlmsController extends Controller
{
    public function __invoke(GeoSettings $geo, SeoSettings $seo, SiteBranding $branding): Response
    {
        // Indulás előtti / karbantartási mód, vagy adminból kikapcsolva → nincs llms.txt.
        if (! $geo->enabled() || ! $seo->searchVisible()) {
            return response("# Not available\n", 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $body = Cache::remember('llms.txt', now()->addHour(), function () use ($geo, $branding) {
            $name = $branding->name();
            $base = rtrim(config('app.url'), '/');

            $lines = [
                "# {$name}",
                '',
                '> '.$geo->description(),
                '',
                '## Fő oldalak',
                "- [Kezdőlap]({$base}/): keresés helyszín, dátum, GPS és térkép szerint",
                "- [Galériák / események]({$base}/events): az összes elérhető fotózás, szűrhető",
                "- [Árak]({$base}/shop): mennyibe kerül egy kép / videó, milyen formátumban",
                "- [Gyakori kérdések]({$base}/faq)",
                "- [Rólunk]({$base}/about): hogyan működik a platform, fotósoknak is",
                "- [Kapcsolat]({$base}/contact)",
                "- [Adatvédelem]({$base}/privacy): GDPR, rendszám-homályosítás",
            ];

            $faq = FaqItem::query()->where('active', true)->orderBy('sort_order')->limit(12)->get(['question_hu', 'answer_hu']);

            if ($faq->isNotEmpty()) {
                $lines[] = '';
                $lines[] = '## Gyakori kérdések és válaszok';
                foreach ($faq as $item) {
                    $lines[] = "- **{$item->question_hu}** {$item->answer_hu}";
                }
            }

            $events = Event::query()
                ->where('status', Event::STATUS_LIVE)
                ->orderByDesc('starts_at')
                ->limit(40)
                ->get(['slug', 'name', 'location', 'event_date']);

            if ($events->isNotEmpty()) {
                $lines[] = '';
                $lines[] = '## Aktuális fotózások';
                foreach ($events as $event) {
                    $date = $event->event_date?->format('Y-m-d');
                    $lines[] = "- [{$event->name} — {$event->location}".($date ? " ({$date})" : '')."]({$base}/events/{$event->slug})";
                }
            }

            return implode("\n", $lines)."\n";
        });

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
