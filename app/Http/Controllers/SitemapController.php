<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\SeoSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Dinamikus sitemap.xml + robots.txt — a statikus infó-oldalak és a publikus
 * (élő) esemény-galériák. A média-részletoldalak szándékosan kimaradnak
 * (nagy darabszám, alacsony SEO-érték, a galéria már lefedi őket).
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $urls = [];

            foreach (['home', 'public.events.index', 'public.about', 'public.shop', 'public.faq', 'public.contact', 'public.privacy', 'public.terms', 'public.impressum'] as $name) {
                $urls[] = ['loc' => route($name), 'changefreq' => 'weekly', 'priority' => $name === 'home' ? '1.0' : '0.6'];
            }

            Event::query()
                ->where('status', Event::STATUS_LIVE)
                ->orderByDesc('updated_at')
                ->limit(10000)
                ->get(['slug', 'updated_at'])
                ->each(function (Event $event) use (&$urls) {
                    $urls[] = [
                        'loc' => route('public.events.show', $event->slug),
                        'lastmod' => $event->updated_at?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ];
                });

            $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            foreach ($urls as $url) {
                $body .= '  <url><loc>'.e($url['loc']).'</loc>'
                    .(isset($url['lastmod']) ? '<lastmod>'.$url['lastmod'].'</lastmod>' : '')
                    .'<changefreq>'.$url['changefreq'].'</changefreq>'
                    .'<priority>'.$url['priority'].'</priority></url>'."\n";
            }

            return $body.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(SeoSettings $seo): Response
    {
        if (! $seo->searchVisible()) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /photographer/',
            'Disallow: /organizer/',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /download/',
            'Disallow: /my-purchases',
            'Disallow: /collection',
            'Disallow: /share/',
            'Disallow: /login',
            'Disallow: /invitations/',
            'Disallow: /adatvedelem/',
            'Disallow: /upload',
            'Disallow: /api/',
            '',
            'Sitemap: '.route('sitemap'),
            '',
            '# AI-kereseknek szant osszefoglalo:',
            '# '.route('llms'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
