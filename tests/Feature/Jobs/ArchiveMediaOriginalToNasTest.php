<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ArchiveMediaOriginalToNas;
use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveMediaOriginalToNasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Storage::fake('local');
        Storage::fake('nas');
    }

    public function test_it_moves_the_original_file_to_the_nas_and_updates_the_media_record(): void
    {
        $photographer = User::factory()->photographer()->create(['name' => 'Kovács Péter']);
        $event = Event::factory()->create([
            'location' => 'Eger',
            'event_date' => '2026-09-04',
        ]);

        Storage::disk('local')->put('originals/1/abc.jpg', 'fake-image-bytes');

        $media = Media::factory()->create([
            'event_id' => $event->id,
            'photographer_id' => $photographer->id,
            'original_s3_key' => 'originals/1/abc.jpg',
            'download_jpeg_s3_key' => null,
            'download_webp_s3_key' => null,
            'original_storage' => Media::STORAGE_LOCAL,
        ]);

        app()->call([new ArchiveMediaOriginalToNas($media->id), 'handle']);

        $media->refresh();

        $expectedPath = "2026-09-04/eger/kovacs-peter/{$media->id}_original.jpg";
        $this->assertSame($expectedPath, $media->original_s3_key);
        Storage::disk('nas')->assertExists($expectedPath);
        Storage::disk('local')->assertMissing('originals/1/abc.jpg');

        $this->assertSame(Media::STORAGE_NAS, $media->original_storage);
        $this->assertNotNull($media->archived_at);
        $this->assertNull($media->archive_error);
    }

    public function test_it_archives_download_variants_alongside_the_original(): void
    {
        $event = Event::factory()->create(['location' => 'Mátraháza', 'event_date' => '2026-08-25']);
        Storage::disk('local')->put('originals/1/x.jpg', 'orig');
        Storage::disk('local')->put('downloads/1/x.jpg', 'jpeg-full');
        Storage::disk('local')->put('downloads/1/x.webp', 'webp-full');

        $media = Media::factory()->create([
            'event_id' => $event->id,
            'original_s3_key' => 'originals/1/x.jpg',
            'download_jpeg_s3_key' => 'downloads/1/x.jpg',
            'download_webp_s3_key' => 'downloads/1/x.webp',
        ]);

        app()->call([new ArchiveMediaOriginalToNas($media->id), 'handle']);

        $media->refresh();

        $this->assertStringStartsWith('2026-08-25/matrahaza/', $media->original_s3_key);
        Storage::disk('nas')->assertExists($media->original_s3_key);
        Storage::disk('nas')->assertExists($media->download_jpeg_s3_key);
        Storage::disk('nas')->assertExists($media->download_webp_s3_key);
        $this->assertStringEndsWith("{$media->id}_download.jpg", $media->download_jpeg_s3_key);
        $this->assertStringEndsWith("{$media->id}_download.webp", $media->download_webp_s3_key);
    }

    public function test_it_is_idempotent_when_already_archived(): void
    {
        $media = Media::factory()->create([
            'original_s3_key' => 'nas/already/there.jpg',
            'original_storage' => Media::STORAGE_NAS,
        ]);

        app()->call([new ArchiveMediaOriginalToNas($media->id), 'handle']);

        $media->refresh();
        $this->assertSame('nas/already/there.jpg', $media->original_s3_key);
    }
}
