<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Videofeldolgozo pipeline (EPIC-05): FFmpeg-alapu thumbnail, vizjelezett
 * 720p elonezet, scrub sprite es MP4 remux/normalizalas.
 *
 * A vizjel csempezeset a mar meglevo ImageProcessingService-hez hasonlo
 * modon, Intervention Image-del rajzoljuk fel egy atlatszo PNG-re, amit
 * FFmpeg overlay filterrel egetunk ra a videora — igy a font/csempe logika
 * konzisztens marad a kepekkel (EPIC-04).
 */
class VideoProcessingService
{
    private ImageManager $manager;

    public function __construct(private WatermarkSettings $watermark)
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * @return array{width: int, height: int, duration: float}
     */
    public function probe(string $absolutePath): array
    {
        $result = Process::timeout(60)->run([
            config('media.ffprobe_binary'),
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $absolutePath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('ffprobe sikertelen: '.$result->errorOutput());
        }

        $data = json_decode($result->output(), true);
        $videoStream = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video');

        if (! $videoStream) {
            throw new RuntimeException('Nem talalhato videosav a fajlban.');
        }

        // Forgatott (portrait) videoknal a rotation metaadat felcsereli a szelesseg/magassagot.
        $rotation = (int) abs($videoStream['tags']['rotate'] ?? $videoStream['side_data_list'][0]['rotation'] ?? 0);
        $width = (int) $videoStream['width'];
        $height = (int) $videoStream['height'];
        if (in_array($rotation, [90, 270], true)) {
            [$width, $height] = [$height, $width];
        }

        return [
            'width' => $width,
            'height' => $height,
            'duration' => (float) ($data['format']['duration'] ?? $videoStream['duration'] ?? 0),
        ];
    }

    /**
     * Egy frame kimentese az adott masodpercnel PNG-kent.
     */
    public function extractFrame(string $videoPath, float $second, string $outputPngPath): void
    {
        $result = Process::timeout(60)->run([
            config('media.ffmpeg_binary'),
            '-y',
            '-ss', (string) max(0, $second),
            '-i', $videoPath,
            '-frames:v', '1',
            $outputPngPath,
        ]);

        if (! $result->successful() || ! is_file($outputPngPath)) {
            throw new RuntimeException('Frame kinyerese sikertelen: '.$result->errorOutput());
        }
    }

    /**
     * 720p (magassag=720, szelesseg aranyos, paros szamra kerekitve) H.264/AAC
     * elonezet, csempezett vizjellel, max 2 Mbps videobitrate-tel.
     */
    public function makeWatermarkedPreview(string $videoPath, int $sourceWidth, int $sourceHeight, string $outputMp4Path): void
    {
        $targetHeight = min(720, $sourceHeight - ($sourceHeight % 2) ?: 720);
        $targetWidth = (int) round($sourceWidth * ($targetHeight / $sourceHeight));
        $targetWidth -= $targetWidth % 2; // libx264 paros szelesseget var el

        $overlayPath = tempnam(sys_get_temp_dir(), 'kf_watermark_').'.png';
        $this->buildTiledWatermarkOverlay($targetWidth, $targetHeight, $overlayPath);

        try {
            $result = Process::timeout((int) config('media.ffmpeg_timeout'))->run([
                config('media.ffmpeg_binary'),
                '-y',
                '-i', $videoPath,
                '-i', $overlayPath,
                '-filter_complex', "[0:v]scale={$targetWidth}:{$targetHeight}[scaled];[scaled][1:v]overlay=0:0:format=auto,format=yuv420p",
                '-c:v', 'libx264',
                '-b:v', '2M',
                '-maxrate', '2M',
                '-bufsize', '4M',
                '-c:a', 'aac',
                '-b:a', '128k',
                '-movflags', '+faststart',
                $outputMp4Path,
            ]);

            if (! $result->successful() || ! is_file($outputMp4Path)) {
                throw new RuntimeException('Vizjelezett elonezet keszitese sikertelen: '.$result->errorOutput());
            }
        } finally {
            File::delete($overlayPath);
        }
    }

    /**
     * Fooldali hero hatter-video: max 1920px szeles, H.264, HANG NELKUL (nemitott
     * autoplay loop hatterhez), ~4 Mbps maxrate-tel es `+faststart`-tal, hogy a
     * fooldal betoltese gyors maradjon. Vizjel NINCS — ez sajat, marketing celu tartalom.
     *
     * @return array{width: int, height: int, duration: float}
     */
    public function makeHeroVideo(string $videoPath, string $outputMp4Path): array
    {
        $result = Process::timeout((int) config('media.ffmpeg_timeout'))->run([
            config('media.ffmpeg_binary'),
            '-y',
            '-i', $videoPath,
            '-an',
            '-vf', "scale='min(1920,iw)':-2:flags=lanczos,format=yuv420p",
            '-c:v', 'libx264',
            '-profile:v', 'high',
            '-preset', 'veryfast',
            '-crf', '25',
            '-maxrate', '4M',
            '-bufsize', '8M',
            '-movflags', '+faststart',
            $outputMp4Path,
        ]);

        if (! $result->successful() || ! is_file($outputMp4Path)) {
            throw new RuntimeException('Hero videó készítése sikertelen: '.$result->errorOutput());
        }

        $probe = $this->probe($outputMp4Path);

        return [
            'width' => $probe['width'],
            'height' => $probe['height'],
            'duration' => $probe['duration'],
        ];
    }

    /**
     * HLS adaptiv stream a VIZJELES elonezet-videobol (EPIC-16): minden
     * `config('media.hls_variants')` szinthez egy kulon m3u8 + .ts szegmensek,
     * plusz egy master.m3u8 ami a savszelesseg alapjan valaszt. A szegmensek
     * `config('media.hls_segment_seconds')` (10 mp) hosszuak.
     *
     * @return array{master: string, files: list<string>} a $outputDir-hez kepesti relativ utak
     */
    public function makeHlsStream(string $videoPath, int $sourceWidth, int $sourceHeight, string $outputDir): array
    {
        File::ensureDirectoryExists($outputDir);

        $segmentSeconds = (int) config('media.hls_segment_seconds', 10);
        $variants = config('media.hls_variants', [720 => '2M', 480 => '1M']);

        $master = ['#EXTM3U', '#EXT-X-VERSION:3'];
        $files = [];

        foreach ($variants as $height => $bitrate) {
            $targetHeight = min((int) $height, $sourceHeight - ($sourceHeight % 2));
            if ($targetHeight < 2) {
                continue;
            }
            $targetWidth = (int) round($sourceWidth * ($targetHeight / $sourceHeight));
            $targetWidth -= $targetWidth % 2;

            $overlayPath = tempnam(sys_get_temp_dir(), 'kf_hls_wm_').'.png';
            $this->buildTiledWatermarkOverlay($targetWidth, $targetHeight, $overlayPath);

            $playlist = "{$height}p.m3u8";
            $segmentPattern = "{$outputDir}/{$height}p_%03d.ts";

            try {
                $result = Process::timeout((int) config('media.ffmpeg_timeout'))->run([
                    config('media.ffmpeg_binary'),
                    '-y',
                    '-i', $videoPath,
                    '-i', $overlayPath,
                    '-filter_complex', "[0:v]scale={$targetWidth}:{$targetHeight}[scaled];[scaled][1:v]overlay=0:0:format=auto,format=yuv420p",
                    '-c:v', 'libx264',
                    '-preset', 'veryfast',
                    '-b:v', $bitrate,
                    '-maxrate', $bitrate,
                    '-bufsize', $this->doubleBitrate($bitrate),
                    '-c:a', 'aac',
                    '-b:a', '128k',
                    '-hls_time', (string) $segmentSeconds,
                    '-hls_playlist_type', 'vod',
                    '-hls_segment_filename', $segmentPattern,
                    '-f', 'hls',
                    "{$outputDir}/{$playlist}",
                ]);

                if (! $result->successful() || ! is_file("{$outputDir}/{$playlist}")) {
                    throw new RuntimeException("HLS ({$height}p) generalasa sikertelen: ".$result->errorOutput());
                }
            } finally {
                File::delete($overlayPath);
            }

            $bandwidth = ((int) rtrim($bitrate, 'M')) * 1_000_000 + 128_000;
            $master[] = "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth},RESOLUTION={$targetWidth}x{$targetHeight}";
            $master[] = $playlist;

            $files[] = $playlist;
            foreach (glob("{$outputDir}/{$height}p_*.ts") ?: [] as $segment) {
                $files[] = basename($segment);
            }
        }

        if (count($files) === 0) {
            throw new RuntimeException('HLS: egyetlen minoségi szint sem keszult el.');
        }

        File::put("{$outputDir}/master.m3u8", implode("\n", $master)."\n");
        $files[] = 'master.m3u8';

        return ['master' => 'master.m3u8', 'files' => $files];
    }

    private function doubleBitrate(string $bitrate): string
    {
        return (((int) rtrim($bitrate, 'M')) * 2).'M';
    }

    /**
     * Scrub sprite: minden $interval masodpercnel egy 160x90 frame, egyetlen
     * PNG sprite sheet-be csempezve. A frontend a sprite kep sajat szelesseget/
     * magassagat 160x90-nel osztva szamolja ki a rács oszlop/sor szamat.
     *
     * @return array{path: string, interval: int}|null null, ha a video tul rovid egy ertelmes sprite-hoz.
     */
    public function makeScrubSprite(string $videoPath, float $duration, string $outputPngPath): ?array
    {
        $interval = 2;

        if ($duration < $interval) {
            return null;
        }

        $frameDir = storage_path('app/tmp/sprite-'.Str::uuid());
        File::ensureDirectoryExists($frameDir);

        try {
            $result = Process::timeout((int) config('media.ffmpeg_timeout'))->run([
                config('media.ffmpeg_binary'),
                '-y',
                '-i', $videoPath,
                '-vf', "fps=1/{$interval},scale=160:90",
                "{$frameDir}/frame_%04d.png",
            ]);

            if (! $result->successful()) {
                throw new RuntimeException('Sprite frame-ek kinyerese sikertelen: '.$result->errorOutput());
            }

            $frames = collect(File::files($frameDir))
                ->sortBy(fn ($f) => $f->getFilename())
                ->values();

            if ($frames->isEmpty()) {
                return null;
            }

            $columns = min(10, $frames->count());
            $rows = (int) ceil($frames->count() / $columns);

            $sprite = $this->manager->createImage($columns * 160, $rows * 90);

            foreach ($frames as $index => $frame) {
                $col = $index % $columns;
                $row = intdiv($index, $columns);
                $sprite->insert($this->manager->decodePath($frame->getPathname()), $col * 160, $row * 90);
            }

            File::put($outputPngPath, (string) $sprite->encode(new PngEncoder));

            return ['path' => $outputPngPath, 'interval' => $interval];
        } finally {
            File::deleteDirectory($frameDir);
        }
    }

    /**
     * Ha az eredeti nem MP4 konteneres (pl. MOV/AVI), atkonvertalja MP4-re —
     * a spec szerint a letoltheto verzio mindig MP4. Eloszor gyors "stream copy"-t
     * probal (nincs mineosegveszteseg, masodperceken belul kesz), ha ez a
     * konteneres/kodek inkompatibilitas miatt nem sikerul, teljes ujrakodolassal probalkozik.
     */
    public function remuxToMp4(string $videoPath, string $outputMp4Path): void
    {
        $copyResult = Process::timeout((int) config('media.ffmpeg_timeout'))->run([
            config('media.ffmpeg_binary'),
            '-y',
            '-i', $videoPath,
            '-c', 'copy',
            '-movflags', '+faststart',
            $outputMp4Path,
        ]);

        if ($copyResult->successful() && is_file($outputMp4Path)) {
            return;
        }

        $reencodeResult = Process::timeout((int) config('media.ffmpeg_timeout'))->run([
            config('media.ffmpeg_binary'),
            '-y',
            '-i', $videoPath,
            '-c:v', 'libx264',
            '-crf', '18',
            '-c:a', 'aac',
            '-b:a', '192k',
            '-movflags', '+faststart',
            $outputMp4Path,
        ]);

        if (! $reencodeResult->successful() || ! is_file($outputMp4Path)) {
            throw new RuntimeException('MP4 remux/ujrakodolas sikertelen: '.$reencodeResult->errorOutput());
        }
    }

    private function buildTiledWatermarkOverlay(int $width, int $height, string $outputPath): void
    {
        $text = $this->watermark->text();
        $fontPath = $this->watermark->fontPath();
        $size = $this->watermark->size();
        $spacing = $this->watermark->spacing();

        $overlay = $this->manager->createImage($width, $height);

        for ($y = -$spacing['y']; $y < $height + $spacing['y']; $y += $spacing['y']) {
            for ($x = -$spacing['x']; $x < $width + $spacing['x']; $x += $spacing['x']) {
                $overlay->text($text, $x, $y, function ($font) use ($fontPath, $size) {
                    $font->filename($fontPath);
                    $font->size($size);
                    $font->color('rgba(255, 255, 255, 0.35)');
                    $font->angle(45);
                    $font->align('center', 'center');
                });
            }
        }

        File::put($outputPath, (string) $overlay->encode(new PngEncoder));
    }
}
