<?php

namespace App\Services;

use App\Models\HeroSlide;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * „Képtár" — az oldal-képek (hero, OG, logó, profilképek, dekor) egy helyen.
 * NEM tartalmazza a médiát (fotók/videók), a vízjeles előnézeteket, a
 * bélyegképeket vagy a letölthető fájlokat — azok a médiakönyvtárban élnek.
 *
 * Csak a `public` disk `hero/ seo/ branding/ avatars/` mappáit nézi.
 */
class PageImageLibrary
{
    /** @var list<string> */
    public const PREFIXES = ['hero', 'seo', 'branding', 'avatars'];

    private const BIG_BYTES = 500_000;

    /**
     * kulcs => [{label, href}] — hol használják.
     *
     * @return array<string, list<array{label: string, href: string}>>
     */
    public function usageMap(): array
    {
        $map = [];
        $add = function (?string $key, string $label, string $href) use (&$map): void {
            $key = trim((string) $key);
            if ($key !== '') {
                $map[$key][] = ['label' => $label, 'href' => $href];
            }
        };

        foreach (HeroSlide::query()->orderBy('sort_order')->get() as $slide) {
            $n = $slide->sort_order + 1;
            $add($slide->image_path, "Hero #{$n}", '/admin/settings/hero');
            $add($slide->poster_path, "Hero #{$n} poszter", '/admin/settings/hero');
        }

        $add(SiteSetting::get('seo_og_image_path'), 'OG megosztókép', '/admin/settings/seo');
        $add(SiteSetting::get('branding_og_auto_path'), 'OG (logóból generált)', '/admin/settings/branding');
        $add(SiteSetting::get('site_logo_path'), 'Fő logó', '/admin/settings/branding');
        $add(SiteSetting::get('site_logo_dark_path'), 'Logó (sötét háttér)', '/admin/settings/branding');

        foreach (User::query()->whereNotNull('avatar_s3_key')->get(['id', 'name', 'avatar_s3_key']) as $user) {
            $add($user->avatar_s3_key, "Profilkép — {$user->name}", "/admin/photographers/{$user->id}");
        }

        return $map;
    }

    /**
     * @return list<array{key: string, url: string, format: string, bytes: int, width: int|null, height: int|null, used: bool, used_by: list<array{label: string, href: string}>, big: bool, convertible: bool}>
     */
    public function all(): array
    {
        $disk = Storage::disk(MediaStorage::public());
        $usage = $this->usageMap();
        $items = [];

        foreach (self::PREFIXES as $prefix) {
            $files = rescue(fn () => $disk->files($prefix), [], false);
            foreach ($files as $key) {
                if (! preg_match('/\.(jpe?g|png|webp|gif|svg)$/i', $key)) {
                    continue;
                }
                $items[] = $this->describe($disk, $key, $usage[$key] ?? []);
            }
        }

        usort($items, fn ($a, $b) => [$b['used'], $b['bytes']] <=> [$a['used'], $a['bytes']]);

        return $items;
    }

    public function convertible(string $key): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif)$/i', $key);
    }

    /**
     * A megadott kulcsok WebP-re konvertálása + a DB-hivatkozások átírása.
     * A régi fájlt NEM törli — árvává válik (a „használatlan" szűrőben látszik,
     * onnan törölhető, ha biztos vagy benne).
     *
     * @param  list<string>  $keys
     * @return array{converted: int, skipped: int, errors: list<string>}
     */
    public function convert(array $keys, int $quality, ?int $maxWidth, ImageProcessingService $images): array
    {
        $disk = Storage::disk(MediaStorage::public());
        $converted = 0;
        $skipped = 0;
        $errors = [];

        foreach ($keys as $key) {
            if (! $this->convertible($key) || ! $disk->exists($key)) {
                $skipped++;

                continue;
            }

            try {
                $result = $images->reencodeWebp($disk->get($key), $quality, $maxWidth);

                $newKey = preg_replace('/\.[^.]+$/', '.webp', $key);
                if ($newKey === $key || $disk->exists($newKey)) {
                    $newKey = preg_replace('/\.[^.]+$/', '', $key).'-'.substr(md5($key.microtime()), 0, 6).'.webp';
                }

                $disk->put($newKey, $result['binary']);
                $this->rewriteReferences($key, $newKey);
                $converted++;
            } catch (\Throwable $e) {
                $errors[] = basename($key).': '.$e->getMessage();
            }
        }

        return ['converted' => $converted, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Törlés — csak árva (nem használt) fájlok.
     *
     * @param  list<string>  $keys
     * @return array{deleted: int, blocked: int}
     */
    public function deleteOrphans(array $keys): array
    {
        $disk = Storage::disk(MediaStorage::public());
        $usage = $this->usageMap();
        $deleted = 0;
        $blocked = 0;

        foreach ($keys as $key) {
            if (isset($usage[$key])) {
                $blocked++;

                continue;
            }
            if ($disk->exists($key)) {
                $disk->delete($key);
                $deleted++;
            }
        }

        return ['deleted' => $deleted, 'blocked' => $blocked];
    }

    /**
     * @param  list<array{label: string, href: string}>  $usedBy
     * @return array{key: string, url: string, format: string, bytes: int, width: int|null, height: int|null, used: bool, used_by: list<array{label: string, href: string}>, big: bool, convertible: bool}
     */
    private function describe($disk, string $key, array $usedBy): array
    {
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $format = $ext === 'jpeg' ? 'jpg' : $ext;
        $bytes = (int) rescue(fn () => $disk->size($key), 0, false);

        [$width, $height] = $this->dimensions($disk, $key, $ext, $bytes);

        return [
            'key' => $key,
            'url' => MediaStorage::publicBaseUrl().'/'.ltrim($key, '/'),
            'format' => $format,
            'bytes' => $bytes,
            'width' => $width,
            'height' => $height,
            'used' => $usedBy !== [],
            'used_by' => $usedBy,
            'big' => $bytes > self::BIG_BYTES,
            'convertible' => $this->convertible($key),
        ];
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions($disk, string $key, string $ext, int $bytes): array
    {
        if ($bytes === 0 || $bytes > 15_000_000) {
            return [null, null];
        }

        try {
            $binary = $disk->get($key);

            if ($ext === 'svg') {
                if (preg_match('/viewBox=["\']\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)/i', $binary, $m)) {
                    return [(int) round((float) $m[1]), (int) round((float) $m[2])];
                }

                return [null, null];
            }

            $info = getimagesizefromstring($binary);

            return $info ? [$info[0], $info[1]] : [null, null];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    private function rewriteReferences(string $oldKey, string $newKey): void
    {
        HeroSlide::query()->where('image_path', $oldKey)->update(['image_path' => $newKey]);
        HeroSlide::query()->where('poster_path', $oldKey)->update(['poster_path' => $newKey]);

        foreach (['seo_og_image_path', 'branding_og_auto_path', 'site_logo_path', 'site_logo_dark_path'] as $settingKey) {
            if (trim((string) SiteSetting::get($settingKey, '')) === $oldKey) {
                SiteSetting::set($settingKey, $newKey);
            }
        }

        User::query()->where('avatar_s3_key', $oldKey)->update(['avatar_s3_key' => $newKey]);
    }
}
