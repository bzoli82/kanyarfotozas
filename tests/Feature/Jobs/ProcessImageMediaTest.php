<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ArchiveMediaOriginalToNas;
use App\Jobs\ProcessImageMedia;
use App\Models\Media;
use App\Services\ImageProcessingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessImageMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('nas');
    }

    private function putFakeOriginal(string $key, int $width = 800, int $height = 600): void
    {
        $file = UploadedFile::fake()->image('original.jpg', $width, $height);
        Storage::disk('local')->put($key, file_get_contents($file->getRealPath()));
    }

    public function test_it_generates_all_variants_and_marks_media_ready(): void
    {
        Bus::fake([ArchiveMediaOriginalToNas::class]);

        $this->putFakeOriginal('originals/1/photo.jpg', 800, 600);

        $media = Media::factory()->create([
            'type' => Media::TYPE_PHOTO,
            'status' => Media::STATUS_PROCESSING,
            'original_s3_key' => 'originals/1/photo.jpg',
            'thumbnail_s3_key' => null,
            'watermarked_s3_key' => null,
            'download_jpeg_s3_key' => null,
            'download_webp_s3_key' => null,
        ]);

        app()->call([new ProcessImageMedia($media->id), 'handle']);

        $media->refresh();

        $this->assertSame(Media::STATUS_READY, $media->status);
        $this->assertSame(800, $media->width);
        $this->assertSame(600, $media->height);

        Storage::disk('public')->assertExists($media->thumbnail_s3_key);
        Storage::disk('public')->assertExists($media->watermarked_s3_key);
        Storage::disk('local')->assertExists($media->download_jpeg_s3_key);
        Storage::disk('local')->assertExists($media->download_webp_s3_key);

        Bus::assertDispatched(ArchiveMediaOriginalToNas::class, fn ($job) => $job->mediaId === $media->id);
    }

    public function test_thumbnail_is_cropped_to_400_by_300(): void
    {
        $this->putFakeOriginal('originals/1/photo.jpg', 1600, 1200);

        $media = Media::factory()->create([
            'type' => Media::TYPE_PHOTO,
            'status' => Media::STATUS_PROCESSING,
            'original_s3_key' => 'originals/1/photo.jpg',
        ]);

        app()->call([new ProcessImageMedia($media->id), 'handle']);
        $media->refresh();

        $processor = app(ImageProcessingService::class);
        $dims = $processor->dimensions(Storage::disk('public')->path($media->thumbnail_s3_key));

        $this->assertSame(400, $dims['width']);
        $this->assertSame(300, $dims['height']);
    }

    public function test_it_ignores_video_media(): void
    {
        $media = Media::factory()->create([
            'type' => Media::TYPE_VIDEO,
            'status' => Media::STATUS_PROCESSING,
            'thumbnail_s3_key' => null,
        ]);

        app()->call([new ProcessImageMedia($media->id), 'handle']);

        $this->assertNull($media->fresh()->thumbnail_s3_key);
    }

    public function test_it_marks_media_as_failed_after_final_attempt(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);

        (new ProcessImageMedia($media->id))->failed(new \RuntimeException('boom'));

        $this->assertSame(Media::STATUS_FAILED, $media->fresh()->status);
    }
}
