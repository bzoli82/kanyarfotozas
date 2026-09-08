<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Services\ImageProcessingService;
use App\Services\MediaStorage;
use App\Services\VideoProcessingService;
use Illuminate\Http\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * A fooldali hero szekcio hatter-mediajanak kezelese (kep VAGY video feltoltes /
 * sorrend / aktivalas / torles) — csak superadmin.
 *
 * Kep: ImageProcessingService::makeHeroImage — max 2560px szeles WebP.
 * Video: VideoProcessingService::makeHeroVideo — max 1920px szeles, hang nelkuli,
 * ~4 Mbps H.264 MP4 + egy WebP poszter-kocka (a video toltese kozben ez latszik).
 * Minden a `public` diskra kerul.
 */
class HeroSlideController extends Controller
{
    /** Ajanlott KEP feltoltesi parameterek — a feluleten is megjelennek. */
    public const IMAGE_WIDTH = 2560;

    public const IMAGE_HEIGHT = 1440;

    public const IMAGE_MAX_KB = 12288; // 12 MB

    /** Ajanlott VIDEO feltoltesi parameterek. */
    public const VIDEO_WIDTH = 1920;

    public const VIDEO_HEIGHT = 1080;

    public const VIDEO_BITRATE_MBPS = 5;

    public const VIDEO_SECONDS = 15;

    public const VIDEO_MAX_KB = 61440; // 60 MB (a rendszer ujrakodolja kisebbre)

    public function index(): InertiaResponse
    {
        return Inertia::render('Admin/Settings/Hero', [
            'slides' => HeroSlide::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (HeroSlide $slide) => [
                    'id' => $slide->id,
                    'type' => $slide->type,
                    'url' => Storage::disk(MediaStorage::public())->url($slide->isVideo() ? $slide->video_path : $slide->image_path),
                    'poster_url' => $slide->poster_path ? Storage::disk(MediaStorage::public())->url($slide->poster_path) : null,
                    'original_filename' => $slide->original_filename,
                    'width' => $slide->width,
                    'height' => $slide->height,
                    'duration_seconds' => $slide->duration_seconds,
                    'is_active' => $slide->is_active,
                    'sort_order' => $slide->sort_order,
                ]),
            'recommended' => [
                'image' => [
                    'width' => self::IMAGE_WIDTH,
                    'height' => self::IMAGE_HEIGHT,
                    'max_mb' => (int) round(self::IMAGE_MAX_KB / 1024),
                ],
                'video' => [
                    'width' => self::VIDEO_WIDTH,
                    'height' => self::VIDEO_HEIGHT,
                    'bitrate_mbps' => self::VIDEO_BITRATE_MBPS,
                    'seconds' => self::VIDEO_SECONDS,
                    'max_mb' => (int) round(self::VIDEO_MAX_KB / 1024),
                ],
            ],
        ]);
    }

    public function store(Request $request, ImageProcessingService $images, VideoProcessingService $videos): RedirectResponse
    {
        $upload = $request->file('file');
        $isVideo = $upload !== null && str_starts_with((string) $upload->getMimeType(), 'video/');

        $request->validate([
            'file' => $isVideo
                ? ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:'.self::VIDEO_MAX_KB]
                : ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.self::IMAGE_MAX_KB, 'dimensions:min_width=1280,min_height=720'],
        ]);

        $uuid = Str::uuid()->toString();

        $isVideo
            ? $this->storeVideo($upload, $uuid, $images, $videos)
            : $this->storeImage($upload, $uuid, $images);

        return back()->with('success', $isVideo ? 'Hero videó feltöltve és feldolgozva.' : 'Hero kép feltöltve.');
    }

    public function update(Request $request, HeroSlide $heroSlide): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $heroSlide->update($data);

        return back()->with('success', 'Hero elem frissítve.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:hero_slides,id'],
        ]);

        foreach ($data['ids'] as $position => $id) {
            HeroSlide::whereKey($id)->update(['sort_order' => $position]);
        }

        return back()->with('success', 'Sorrend mentve.');
    }

    public function destroy(HeroSlide $heroSlide): RedirectResponse
    {
        Storage::disk(MediaStorage::public())->delete(array_filter([
            $heroSlide->image_path,
            $heroSlide->video_path,
            $heroSlide->poster_path,
        ]));

        $heroSlide->delete();

        return back()->with('success', 'Hero elem törölve.');
    }

    private function storeImage(UploadedFile $upload, string $uuid, ImageProcessingService $images): void
    {
        $hero = $images->makeHeroImage($upload->getRealPath());

        $path = "hero/{$uuid}.webp";
        Storage::disk(MediaStorage::public())->put($path, $hero['binary']);

        HeroSlide::create([
            'type' => HeroSlide::TYPE_IMAGE,
            'image_path' => $path,
            'original_filename' => $upload->getClientOriginalName(),
            'width' => $hero['width'],
            'height' => $hero['height'],
            'sort_order' => (int) HeroSlide::max('sort_order') + 1,
            'is_active' => true,
        ]);
    }

    private function storeVideo(
        UploadedFile $upload,
        string $uuid,
        ImageProcessingService $images,
        VideoProcessingService $videos,
    ): void {
        $tmpDir = storage_path('app/tmp/hero-'.$uuid);
        FileFacade::ensureDirectoryExists($tmpDir);

        try {
            $mp4 = "{$tmpDir}/hero.mp4";
            $meta = $videos->makeHeroVideo($upload->getRealPath(), $mp4);

            $posterPng = "{$tmpDir}/poster.png";
            $videos->extractFrame($mp4, min(1.0, $meta['duration']), $posterPng);
            $poster = $images->makeHeroImage($posterPng);

            $videoPath = "hero/videos/{$uuid}.mp4";
            $posterPath = "hero/{$uuid}.webp";

            Storage::disk(MediaStorage::public())->putFileAs('hero/videos', new File($mp4), "{$uuid}.mp4");
            Storage::disk(MediaStorage::public())->put($posterPath, $poster['binary']);

            HeroSlide::create([
                'type' => HeroSlide::TYPE_VIDEO,
                'video_path' => $videoPath,
                'poster_path' => $posterPath,
                'original_filename' => $upload->getClientOriginalName(),
                'width' => $meta['width'],
                'height' => $meta['height'],
                'duration_seconds' => (int) round($meta['duration']),
                'sort_order' => (int) HeroSlide::max('sort_order') + 1,
                'is_active' => true,
            ]);
        } finally {
            FileFacade::deleteDirectory($tmpDir);
        }
    }
}
