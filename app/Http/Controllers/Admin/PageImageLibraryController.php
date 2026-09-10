<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageProcessingService;
use App\Services\PageImageLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Képtár (/admin/settings/images, superadmin) — az oldal-képek (hero, OG, logó,
 * profilképek) egy rácsban: formátum, méret, hol használják; JPG/PNG → WebP
 * csoportos konvertálás (a DB-hivatkozásokat is átírja) + árva-takarítás.
 * A médiát / vízjeles előnézeteket / letölthető fájlokat NEM érinti.
 */
class PageImageLibraryController extends Controller
{
    public function index(PageImageLibrary $library): InertiaResponse
    {
        $images = $library->all();

        return Inertia::render('Admin/Settings/ImageLibrary', [
            'images' => $images,
            'summary' => [
                'total' => count($images),
                'orphans' => count(array_filter($images, fn ($i) => ! $i['used'])),
                'convertible' => count(array_filter($images, fn ($i) => $i['convertible'])),
                'big' => count(array_filter($images, fn ($i) => $i['big'])),
            ],
        ]);
    }

    public function convert(Request $request, PageImageLibrary $library, ImageProcessingService $images): RedirectResponse
    {
        $data = $request->validate([
            'keys' => ['required', 'array', 'min:1', 'max:100'],
            'keys.*' => ['string'],
            'quality' => ['required', 'integer', 'min:40', 'max:100'],
            'max_width' => ['nullable', 'integer', 'min:200', 'max:4000'],
        ]);

        $result = $library->convert($data['keys'], $data['quality'], $data['max_width'] ?? null, $images);

        $msg = "{$result['converted']} kép WebP-re konvertálva (a hivatkozások frissültek).";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} kihagyva (nem konvertálható).";
        }
        if ($result['errors'] !== []) {
            $msg .= ' Hibák: '.implode('; ', $result['errors']);
        }
        $msg .= ' A régi fájlok árvává váltak — a „használatlan" szűrőben törölheted őket.';

        return back()->with('success', $msg);
    }

    public function destroy(Request $request, PageImageLibrary $library): RedirectResponse
    {
        $data = $request->validate([
            'keys' => ['required', 'array', 'min:1', 'max:200'],
            'keys.*' => ['string'],
        ]);

        $result = $library->deleteOrphans($data['keys']);

        $msg = "{$result['deleted']} fájl törölve.";
        if ($result['blocked'] > 0) {
            $msg .= " {$result['blocked']} kihagyva — még használatban van (előbb a beállításnál cseréld le).";
        }

        return back()->with('success', $msg);
    }
}
