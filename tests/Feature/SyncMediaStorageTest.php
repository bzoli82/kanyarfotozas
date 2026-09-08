<?php

namespace Tests\Feature;

use App\Models\Media;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncMediaStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('r2_public');
        Storage::fake('r2_private');
    }

    public function test_it_copies_public_and_large_files_to_the_configured_disks(): void
    {
        config([
            'media.disks.public' => 'r2_public',
            'media.disks.archive' => 'r2_private',
        ]);

        $media = Media::factory()->photo()->create([
            'thumbnail_s3_key' => 'thumbnails/1.webp',
            'watermarked_s3_key' => 'watermarked/1.webp',
            'original_s3_key' => 'originals/1/a.jpg',
            'download_jpeg_s3_key' => 'downloads/1.jpg',
            'download_webp_s3_key' => 'downloads/1.webp',
            'original_storage' => Media::STORAGE_LOCAL,
        ]);

        Storage::disk('public')->put('thumbnails/1.webp', 'thumb');
        Storage::disk('public')->put('watermarked/1.webp', 'wm');
        Storage::disk('local')->put('originals/1/a.jpg', 'orig');
        Storage::disk('local')->put('downloads/1.jpg', 'jpg');
        Storage::disk('local')->put('downloads/1.webp', 'webp');

        $this->artisan('kanyarfotozas:sync-media-storage', ['--from-public' => 'public', '--from-archive' => 'local'])
            ->assertSuccessful();

        Storage::disk('r2_public')->assertExists('thumbnails/1.webp');
        Storage::disk('r2_public')->assertExists('watermarked/1.webp');
        Storage::disk('r2_private')->assertExists('originals/1/a.jpg');
        Storage::disk('r2_private')->assertExists('downloads/1.jpg');
        Storage::disk('r2_private')->assertExists('downloads/1.webp');

        $this->assertSame(Media::STORAGE_NAS, $media->fresh()->original_storage);
    }

    public function test_dry_run_copies_nothing(): void
    {
        config(['media.disks.public' => 'r2_public', 'media.disks.archive' => 'r2_private']);

        Media::factory()->photo()->create([
            'thumbnail_s3_key' => 'thumbnails/9.webp',
            'original_storage' => Media::STORAGE_LOCAL,
        ]);
        Storage::disk('public')->put('thumbnails/9.webp', 'x');

        $this->artisan('kanyarfotozas:sync-media-storage', ['--from-public' => 'public', '--dry-run' => true])
            ->assertSuccessful();

        Storage::disk('r2_public')->assertMissing('thumbnails/9.webp');
    }
}
