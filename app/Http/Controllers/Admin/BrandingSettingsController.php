<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageProcessingService;
use App\Services\MediaStorage;
use App\Services\SiteBranding;
use App\Support\SvgSanitizer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Az oldal neve / márkajele (/admin/settings/branding) — csak superadmin.
 * Egy helyen szerkeszthető: a fejléc logó (szöveg VAGY feltöltött kép), az
 * oldalcímek, az e-mailek és a vízjel szövege is innen jön.
 */
class BrandingSettingsController extends Controller
{
    private const LOGO_MAX_KB = 1024;

    public function index(SiteBranding $branding): InertiaResponse
    {
        return Inertia::render('Admin/Settings/Branding', [
            'branding' => $branding->toArray(),
            'watermarkPreview' => $branding->watermarkText(),
        ]);
    }

    public function update(Request $request, SiteBranding $branding, ImageProcessingService $images): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'logo_lead' => ['required', 'string', 'max:30'],
            'logo_tail' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'file', 'mimes:svg,png,webp', 'max:'.self::LOGO_MAX_KB],
            'logo_dark' => ['nullable', 'file', 'mimes:svg,png,webp', 'max:'.self::LOGO_MAX_KB],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_logo_dark' => ['sometimes', 'boolean'],
        ]);

        $branding->update($data['name'], $data['logo_lead'], $data['logo_tail'] ?? '');

        $disk = Storage::disk(MediaStorage::public());

        $touchedLogo = $request->hasFile('logo') || $request->hasFile('logo_dark')
            || $request->boolean('remove_logo') || $request->boolean('remove_logo_dark');

        $this->handleSlot($request, $disk, $images, 'logo', 'remove_logo', $branding->logoPath(), $branding->setLogoPath(...));
        $this->handleSlot($request, $disk, $images, 'logo_dark', 'remove_logo_dark', $branding->logoDarkPath(), $branding->setLogoDarkPath(...));

        if ($touchedLogo) {
            $this->regenerateOgImage($branding, $disk, $images);
        }

        return back()->with('success', 'Márkajel elmentve.');
    }

    /**
     * @param  Filesystem  $disk
     * @param  callable(?string): void  $persist
     */
    private function handleSlot(Request $request, $disk, ImageProcessingService $images, string $field, string $removeField, ?string $currentKey, callable $persist): void
    {
        if ($request->boolean($removeField) && $currentKey) {
            $disk->delete($currentKey);
            $persist(null);

            return;
        }

        if (! $request->hasFile($field)) {
            return;
        }

        if ($currentKey) {
            $disk->delete($currentKey);
        }

        $file = $request->file($field);
        $slug = Str::lower(Str::random(10));

        if (strtolower((string) $file->getClientOriginalExtension()) === 'svg') {
            $clean = SvgSanitizer::clean((string) file_get_contents($file->getRealPath()));

            if ($clean === null) {
                return;
            }

            $key = "branding/logo-{$slug}.svg";
            $disk->put($key, $clean, ['ContentType' => 'image/svg+xml']);
        } else {
            $key = "branding/logo-{$slug}.png";
            $disk->put($key, $images->makeLogo($file->getRealPath()));
        }

        $persist($key);
    }

    /**
     * OG megosztókép újragenerálása a logóból. A sötét-változat (világos rajz) →
     * sötét vászon; egyébként a fő logó → világos vászon. SVG-nél kimarad (a
     * kézzel feltöltött / beépített OG-kép marad érvényben).
     *
     * @param  Filesystem  $disk
     */
    private function regenerateOgImage(SiteBranding $branding, $disk, ImageProcessingService $images): void
    {
        if ($branding->ogAutoPath()) {
            $disk->delete($branding->ogAutoPath());
            $branding->setOgAutoPath(null);
        }

        [$src, $bg] = $branding->logoDarkPath()
            ? [$branding->logoDarkPath(), '#0d0d0d']
            : [$branding->logoPath(), '#f7f7f8'];

        if ($src === null || ! preg_match('/\.(png|jpe?g|webp)$/i', $src)) {
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'og').'.'.pathinfo($src, PATHINFO_EXTENSION);
        file_put_contents($tmp, $disk->get($src));

        try {
            $key = 'branding/og-'.Str::lower(Str::random(10)).'.png';
            $disk->put($key, $images->makeOgFromLogo($tmp, $bg));
            $branding->setOgAutoPath($key);
        } finally {
            @unlink($tmp);
        }
    }
}
