<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Media;
use App\Models\MediaShare;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Publikus oldalak meta-adatai (title / description / canonical / OG / Twitter /
 * robots) egy helyen. A blade (első lefestés + a JS-t nem futtató közösségi
 * "link-preview" botok) és a HandleInertiaRequests shared prop (SPA-navigáció)
 * is innen olvas, hogy a kettő sose térjen el.
 */
class Seo
{
    /**
     * Route-nevek, amelyeket nem indexelünk (vékony / privát / tranzakciós oldalak).
     *
     * @var list<string>
     */
    private const NOINDEX_ROUTES = [
        'public.cart', 'public.checkout.store', 'public.checkout.success',
        'public.checkout.cancel', 'public.checkout.simplepay.return',
        'public.download.show', 'public.my-purchases', 'public.collection',
        'public.collection.shared', 'public.share', 'public.unsubscribe',
        'public.data-request', 'public.data-request.verify',
        'login', 'invitations.show', 'mobile-upload',
    ];

    public function __construct(
        private SiteBranding $branding,
        private SeoSettings $settings,
    ) {}

    /**
     * @return array{
     *   title: string, description: string, canonical: string, image: string,
     *   type: string, robots: string, site_name: string, locale: string,
     *   google_verification: ?string, jsonld: array<int, array<string, mixed>>
     * }
     */
    public function forRequest(Request $request): array
    {
        $brand = $this->branding->name();
        $route = $request->route()?->getName();

        $searchVisible = $this->settings->searchVisible();

        $seo = [
            'title' => $brand.' — '.$this->settings->titleSuffix(),
            'description' => $this->settings->description(),
            'canonical' => $this->canonical($request),
            'image' => $this->settings->ogImageUrl(),
            'type' => 'website',
            'robots' => ! $searchVisible
                ? 'noindex, nofollow'
                : (in_array($route, self::NOINDEX_ROUTES, true) ? 'noindex, follow' : 'index, follow'),
            'site_name' => $brand,
            'locale' => str_replace('_', '-', app()->getLocale()),
            'google_verification' => $searchVisible ? $this->settings->googleVerification() : null,
            'jsonld' => $route === 'home' ? $this->siteJsonLd($brand) : [],
        ];

        return match ($route) {
            'public.events.show' => $this->event($request, $seo),
            'public.media.show' => $this->media($request, $seo),
            'public.share' => $this->share($request, $seo),
            'public.events.index' => [...$seo,
                'title' => 'Fotózások és galériák — '.$brand,
                'description' => 'Böngészd a lefotózott pályanapokat és versenyeket. Szűrj helyszínre, országra, dátumra vagy a saját GPS-pozíciódra.',
            ],
            'public.about' => [...$seo, 'title' => 'Rólunk — '.$brand, 'description' => 'Kik vagyunk, hogyan dolgozunk, és hogyan lehetsz te is fotósunk.'],
            'public.community' => [...$seo, 'title' => 'Közösség — '.$brand, 'description' => 'Kövess minket a közösségi oldalainkon: friss képek az eseményekről és a következő fotózások.'],
            'public.shop' => [...$seo, 'title' => 'Árak és letöltés — '.$brand, 'description' => 'Egységes árazás, azonnali letöltés vízjel nélkül, JPEG és WebP formátum, videók MP4-ben.'],
            'public.faq' => [...$seo, 'title' => 'Gyakori kérdések — '.$brand, 'description' => 'Válaszok a vásárlással, letöltéssel, rendszám-homályosítással és a fotósoknak szóló kérdésekre.'],
            'public.contact' => [...$seo, 'title' => 'Kapcsolat — '.$brand, 'description' => 'Írj nekünk kérdéssel, panasszal vagy együttműködési ajánlattal.'],
            'public.privacy' => [...$seo, 'title' => 'Adatkezelési tájékoztató — '.$brand],
            default => $seo,
        };
    }

    /**
     * @param  array<string, string>  $seo
     * @return array<string, string>
     */
    private function event(Request $request, array $seo): array
    {
        $event = $request->route('event');

        if (! $event instanceof Event) {
            return $seo;
        }

        // Figyelem: a withCoverThumbnail() addSelect-et használ — utána nem szabad
        // ->value()/->select() hívás, mert kitörli az alias oszlopot.
        $cover = Event::query()->withCoverThumbnail()->whereKey($event->id)->first()?->cover_thumbnail_s3_key;
        $where = trim(implode(', ', array_filter([$event->location, $event->country?->name])));
        $image = $cover ? $this->mediaUrl($cover) : $seo['image'];

        return [
            ...$seo,
            'title' => $event->name.' — '.$this->branding->name(),
            'description' => Str::limit(trim(($event->name)
                .($where ? " · {$where}" : '')
                .($event->event_date ? ' · '.$event->event_date->format('Y. m. d.') : '')
                .' — nézd meg és töltsd le a köreidről készült fotókat és videókat.'), 300),
            'image' => $image,
            'type' => 'website',
            'jsonld' => [array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Event',
                'name' => $event->name,
                'startDate' => $event->event_date?->toDateString(),
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'location' => $where ? ['@type' => 'Place', 'name' => $where] : null,
                'image' => $image,
                'url' => $this->absolute($request->getPathInfo()),
            ])],
        ];
    }

    /**
     * @param  array<string, string>  $seo
     * @return array<string, string>
     */
    private function media(Request $request, array $seo): array
    {
        $media = $request->route('media');

        if (! $media instanceof Media) {
            return $seo;
        }

        $media->loadMissing('event:id,name,location');
        $key = $media->watermarked_s3_key ?: $media->thumbnail_s3_key;

        return [
            ...$seo,
            'title' => ($media->event?->name ? $media->event->name.' — ' : '').$this->branding->name(),
            'image' => $key ? $this->mediaUrl($key) : $seo['image'],
        ];
    }

    /**
     * @param  array<string, string>  $seo
     * @return array<string, string>
     */
    private function share(Request $request, array $seo): array
    {
        $token = $request->route('token');
        $share = MediaShare::query()->where('share_token', $token)->with('media.event:id,name')->first();

        if (! $share || ! $share->media) {
            return $seo;
        }

        $key = $share->media->watermarked_s3_key ?: $share->media->thumbnail_s3_key;

        return [
            ...$seo,
            'title' => ($share->media->event?->name ? $share->media->event->name.' — ' : '').$this->branding->name(),
            'description' => 'Nézd meg ezt a felvételt, és vásárold meg vízjel nélkül.',
            'image' => $key ? $this->mediaUrl($key) : $seo['image'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function siteJsonLd(string $brand): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $brand,
                'url' => $base,
                'inLanguage' => 'hu',
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $brand,
                'url' => $base,
                'logo' => $this->absolute('/pwa-512.png'),
            ],
        ];
    }

    private function canonical(Request $request): string
    {
        // Query string nélkül — a szűrt/lapozott listák ne osszák meg a rangsort.
        return $this->absolute($request->getPathInfo());
    }

    private function absolute(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    private function mediaUrl(string $key): string
    {
        $base = MediaStorage::publicBaseUrl();

        return Str::startsWith($base, ['http://', 'https://'])
            ? $base.'/'.ltrim($key, '/')
            : $this->absolute($base.'/'.ltrim($key, '/'));
    }
}
