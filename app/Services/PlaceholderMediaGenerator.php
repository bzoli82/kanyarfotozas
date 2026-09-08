<?php

namespace App\Services;

use App\Models\HeroSlide;
use App\Models\Media;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Fejlesztoi/demo segedosztaly: minden seedelt Media rekordhoz legyart egy
 * felismerheto "placeholder" kepet (szines hatter + esemeny neve + sorszam),
 * majd atengedi a valodi ImageProcessingService pipeline-on, hogy a thumbnail /
 * vizjelezett elonezet / letoltheto valtozatok ugyanugy nezzenek ki, mint egy
 * igazi feltoltesnel. Videohoz rovid, statikus-zoomos MP4-et + scrub sprite-ot
 * general FFmpeg-gel. NEM production kod — csak a demo galeria feltoltesehez.
 */
class PlaceholderMediaGenerator
{
    private const PLACEHOLDER_DURATION = 8;

    private const SPRITE_INTERVAL = 2;

    /** @var string[] Sotet, telitett hatterszinek — esemenynkent determinisztikusan valasztva. */
    private array $palette = ['#1f3a5f', '#5f1f2e', '#264d33', '#4a3a1f', '#3d1f4a', '#1f4a4a', '#4a2a1f', '#2a2f4a'];

    private ImageManager $manager;

    public function __construct(private ImageProcessingService $images, private VideoProcessingService $video)
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * @return list<string> a legeneralt/felulirt fajl-kulcsok (naplozashoz)
     */
    public function generateForMedia(Media $media, bool $force = false): array
    {
        $media->loadMissing('event');

        if (! $force && Storage::disk(MediaStorage::public())->exists((string) $media->thumbnail_s3_key)) {
            return [];
        }

        $seed = crc32(($media->event?->name ?? 'esemeny').':'.$media->id);
        $color = $this->palette[$seed % count($this->palette)];
        $title = $media->event?->name ?? app(SiteBranding::class)->name();
        $subtitle = strtoupper(($media->isVideo() ? 'Videó' : 'Fotó').' · #'.$media->id);

        $card = $this->makeCard(1600, 1067, $color, $title, $subtitle);

        $tmpJpeg = tempnam(sys_get_temp_dir(), 'kf_ph_').'.jpg';
        file_put_contents($tmpJpeg, (string) $card->encode(new JpegEncoder(quality: 90)));

        $written = [];

        try {
            Storage::disk(MediaStorage::public())->put($media->thumbnail_s3_key, $this->images->makeThumbnail($tmpJpeg));
            $written[] = $media->thumbnail_s3_key;

            if ($media->isPhoto()) {
                $written = array_merge($written, $this->generatePhotoVariants($media, $tmpJpeg));
            } else {
                $written = array_merge($written, $this->generateVideoVariants($media, $tmpJpeg));
            }

            $media->forceFill([
                'width' => 1600,
                'height' => 1067,
                'status' => Media::STATUS_READY,
            ])->save();
        } finally {
            @unlink($tmpJpeg);
        }

        return $written;
    }

    /**
     * @return list<string>
     */
    private function generatePhotoVariants(Media $media, string $tmpJpeg): array
    {
        Storage::disk(MediaStorage::public())->put($media->watermarked_s3_key, $this->images->makeWatermarkedPreview($tmpJpeg));
        Storage::disk('local')->put($media->download_jpeg_s3_key, $this->images->makeDownloadJpeg($tmpJpeg));
        Storage::disk('local')->put($media->download_webp_s3_key, $this->images->makeDownloadWebp($tmpJpeg));
        Storage::disk('local')->put($media->original_s3_key, (string) file_get_contents($tmpJpeg));

        return [$media->watermarked_s3_key, $media->download_jpeg_s3_key, $media->download_webp_s3_key, $media->original_s3_key];
    }

    /**
     * @return list<string>
     */
    private function generateVideoVariants(Media $media, string $tmpJpeg): array
    {
        $tmpMp4 = tempnam(sys_get_temp_dir(), 'kf_ph_').'.mp4';
        $tmpSprite = tempnam(sys_get_temp_dir(), 'kf_ph_').'.png';

        try {
            $ffmpeg = config('media.ffmpeg_binary');

            // Statikus placeholder-kepbol 8 mp-es klip, lassu fuggoleges lebegessel (hover-play-nel latszik, hogy video)
            Process::timeout(120)->run([
                $ffmpeg, '-y', '-loop', '1', '-i', $tmpJpeg,
                '-t', (string) self::PLACEHOLDER_DURATION,
                '-vf', "scale=1280:-1,crop=1280:720:0:'(ih-720)/2 + 50*sin(t/2)',format=yuv420p",
                '-c:v', 'libx264', '-r', '25', '-an',
                $tmpMp4,
            ])->throw();

            $frames = (int) ceil(self::PLACEHOLDER_DURATION / self::SPRITE_INTERVAL);
            $cols = min(10, $frames);
            $rows = (int) ceil($frames / $cols);

            Process::timeout(120)->run([
                $ffmpeg, '-y', '-i', $tmpMp4,
                '-vf', 'fps=1/'.self::SPRITE_INTERVAL.",scale=160:90,tile={$cols}x{$rows}",
                '-frames:v', '1',
                $tmpSprite,
            ])->throw();

            Storage::disk(MediaStorage::public())->put($media->watermarked_s3_key, (string) file_get_contents($tmpMp4));
            Storage::disk(MediaStorage::public())->put($media->preview_sprite_s3_key, (string) file_get_contents($tmpSprite));
            Storage::disk('local')->put($media->original_s3_key, (string) file_get_contents($tmpMp4));

            $written = [$media->watermarked_s3_key, $media->preview_sprite_s3_key, $media->original_s3_key];

            // HLS adaptiv stream (EPIC-16)
            $hlsKey = null;
            $hlsDir = sys_get_temp_dir().'/kf_hls_'.$media->id;
            try {
                $hls = $this->video->makeHlsStream($tmpMp4, 1280, 720, $hlsDir);
                foreach ($hls['files'] as $rel) {
                    Storage::disk(MediaStorage::public())->put("hls/{$media->id}/{$rel}", (string) file_get_contents("{$hlsDir}/{$rel}"));
                    $written[] = "hls/{$media->id}/{$rel}";
                }
                $hlsKey = "hls/{$media->id}/{$hls['master']}";
            } catch (\Throwable $e) {
                // demo — HLS opcionalis
            } finally {
                if (is_dir($hlsDir)) {
                    array_map('unlink', glob("{$hlsDir}/*") ?: []);
                    @rmdir($hlsDir);
                }
            }

            $media->forceFill([
                'duration_seconds' => self::PLACEHOLDER_DURATION,
                'preview_sprite_interval' => self::SPRITE_INTERVAL,
                'hls_playlist_s3_key' => $hlsKey,
            ])->save();

            return $written;
        } finally {
            @unlink($tmpMp4);
            @unlink($tmpSprite);
        }
    }

    /**
     * Demo hero hatterkepek a fooldalhoz (nagy felbontasu, szoveg nelkuli
     * szines kartyak) — a DemoDataSeeder hivja. Ureses `hero_slides` eseten fut le.
     *
     * @return list<string> a legeneralt fajl-kulcsok
     */
    public function generateHeroSlides(int $count = 3): array
    {
        if (HeroSlide::query()->exists()) {
            return [];
        }

        $written = [];

        for ($i = 0; $i < $count; $i++) {
            $color = $this->palette[($i * 3) % count($this->palette)];
            $card = $this->makeCard(2560, 1440, $color, '', '');

            $tmp = tempnam(sys_get_temp_dir(), 'kf_hero_').'.jpg';
            file_put_contents($tmp, (string) $card->encode(new JpegEncoder(quality: 90)));

            try {
                $hero = $this->images->makeHeroImage($tmp);
                $path = 'hero/'.Str::uuid()->toString().'.webp';
                Storage::disk(MediaStorage::public())->put($path, $hero['binary']);

                HeroSlide::create([
                    'type' => HeroSlide::TYPE_IMAGE,
                    'image_path' => $path,
                    'original_filename' => 'demo-hero-'.($i + 1).'.jpg',
                    'width' => $hero['width'],
                    'height' => $hero['height'],
                    'sort_order' => $i,
                    'is_active' => true,
                ]);

                $written[] = $path;
            } finally {
                @unlink($tmp);
            }
        }

        return $written;
    }

    private function makeCard(int $width, int $height, string $color, string $title, string $subtitle): ImageInterface
    {
        $image = $this->manager->createImage($width, $height)->fill($color);

        // Atlos, kicsit vilagosabb sav a lapos szin megtoresehez
        $image->drawPolygon(function ($polygon) use ($width, $height) {
            $polygon->point(0, (int) ($height * 0.55));
            $polygon->point($width, (int) ($height * 0.2));
            $polygon->point($width, $height);
            $polygon->point(0, $height);
            $polygon->background('rgba(255, 255, 255, 0.06)');
        });

        $fontPath = resource_path('fonts/Arimo-Bold.ttf');

        if ($title !== '') {
            $image->text(mb_strtoupper($title), (int) ($width / 2), (int) ($height / 2) - 30, function ($font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(84);
                $font->color('#ffffff');
                $font->align('center', 'center');
            });
        }

        if ($subtitle !== '') {
            $image->text($subtitle, (int) ($width / 2), (int) ($height / 2) + 60, function ($font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(34);
                $font->color('rgba(255, 255, 255, 0.6)');
                $font->align('center', 'center');
            });
        }

        $image->text(mb_strtoupper(app(SiteBranding::class)->name()).' · DEMO', (int) ($width / 2), $height - 60, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(24);
            $font->color('rgba(255, 255, 255, 0.4)');
            $font->align('center', 'center');
        });

        return $image;
    }
}
