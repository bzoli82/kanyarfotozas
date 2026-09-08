<?php

namespace Tests\Feature;

use App\Jobs\ArchiveMediaOriginalToNas;
use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use App\Services\MediaStorage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A média tároló diskek env-vezérelt indirekciója (Cloudflare R2 átállás).
 */
class MediaStorageDiskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_disk_names_come_from_config(): void
    {
        $this->assertSame('public', MediaStorage::public());
        $this->assertSame('nas', MediaStorage::archive());
        $this->assertTrue(MediaStorage::hasArchiveTier());

        config([
            'media.disks.public' => 'r2_public',
            'media.disks.archive' => 'r2_private',
        ]);

        $this->assertSame('r2_public', MediaStorage::public());
        $this->assertSame('r2_private', MediaStorage::archive());
    }

    public function test_no_archive_tier_when_archive_disk_equals_staging(): void
    {
        config(['media.disks.archive' => 'local']);

        $this->assertFalse(MediaStorage::hasArchiveTier());
    }

    public function test_media_base_url_reflects_configured_public_disk(): void
    {
        // Lokális disk -> relatív bázis.
        $this->assertSame('/storage', MediaStorage::publicBaseUrl());

        config([
            'media.disks.public' => 'r2_public',
            'filesystems.disks.r2_public.url' => 'https://media.example.com/',
        ]);

        $this->assertSame('https://media.example.com', MediaStorage::publicBaseUrl());
    }

    public function test_inertia_shares_media_base_url(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertSame('/storage', $response->viewData('page')['props']['mediaBaseUrl']);
    }

    public function test_archive_job_writes_to_the_configured_archive_disk(): void
    {
        Storage::fake('local');
        Storage::fake('r2_private');
        config(['media.disks.archive' => 'r2_private']);

        $photographer = User::factory()->photographer()->create(['name' => 'Kovács Péter']);
        $event = Event::factory()->create(['location' => 'Eger', 'event_date' => '2026-09-04']);

        Storage::disk('local')->put('originals/1/abc.jpg', 'bytes');

        $media = Media::factory()->photo()->create([
            'event_id' => $event->id,
            'photographer_id' => $photographer->id,
            'original_s3_key' => 'originals/1/abc.jpg',
            'download_jpeg_s3_key' => null,
            'download_webp_s3_key' => null,
            'original_storage' => Media::STORAGE_LOCAL,
        ]);

        app()->call([new ArchiveMediaOriginalToNas($media->id), 'handle']);

        $media->refresh();
        $expected = "2026-09-04/eger/kovacs-peter/{$media->id}_original.jpg";
        Storage::disk('r2_private')->assertExists($expected);
        Storage::disk('local')->assertMissing('originals/1/abc.jpg');
        $this->assertSame(Media::STORAGE_NAS, $media->original_storage);
    }

    public function test_archive_job_is_a_noop_without_an_archive_tier(): void
    {
        Storage::fake('local');
        config(['media.disks.archive' => 'local']);

        $media = Media::factory()->photo()->create([
            'original_s3_key' => 'originals/1/abc.jpg',
            'original_storage' => Media::STORAGE_LOCAL,
        ]);
        Storage::disk('local')->put('originals/1/abc.jpg', 'bytes');

        app()->call([new ArchiveMediaOriginalToNas($media->id), 'handle']);

        $media->refresh();
        $this->assertSame(Media::STORAGE_LOCAL, $media->original_storage);
        Storage::disk('local')->assertExists('originals/1/abc.jpg');
    }
}
