<?php

namespace Tests\Feature\Admin;

use App\Jobs\SyncArchiveMediaChunk;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ArchiveStorage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function configureNas(): void
    {
        SiteSetting::set('nas_host', 'nas.example.test');
        SiteSetting::set('nas_username', 'roadside');
        SiteSetting::set('nas_password', Crypt::encryptString('secret'));
        SiteSetting::set('nas_root', '/roadsidephoto');
    }

    private function configureR2(): void
    {
        SiteSetting::set('r2_endpoint', 'https://acc.r2.cloudflarestorage.com');
        SiteSetting::set('r2_access_key_id', 'key');
        SiteSetting::set('r2_secret_access_key', Crypt::encryptString('secret'));
        SiteSetting::set('r2_public_bucket', 'rp-public');
        SiteSetting::set('r2_private_bucket', 'rp-private');
    }

    public function test_non_superadmin_cannot_change_the_archive_disk(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/settings/storage/archive-disk', ['disk' => 'r2_private'])
            ->assertForbidden();
    }

    public function test_superadmin_switches_the_archive_disk_and_config_reflects_it(): void
    {
        $this->configureR2();

        $this->actingAs(User::factory()->superadmin()->create())
            ->put('/admin/settings/storage/archive-disk', ['disk' => 'r2_private'])
            ->assertRedirect();

        $this->assertSame('r2_private', SiteSetting::get('media_archive_disk'));

        app(ArchiveStorage::class)->applyRuntimeConfig();
        $this->assertSame('r2_private', config('media.disks.archive'));
    }

    public function test_switching_to_an_unconfigured_disk_is_rejected(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())
            ->from('/admin/settings/storage')
            ->put('/admin/settings/storage/archive-disk', ['disk' => 'r2_private'])
            ->assertRedirect('/admin/settings/storage')
            ->assertSessionHas('error');

        $this->assertNull(app(ArchiveStorage::class)->configuredDisk());
    }

    public function test_clearing_the_choice_falls_back_to_the_env_default(): void
    {
        SiteSetting::set('media_archive_disk', 'r2_private');

        $this->actingAs(User::factory()->superadmin()->create())
            ->put('/admin/settings/storage/archive-disk', ['disk' => null])
            ->assertRedirect();

        $this->assertNull(app(ArchiveStorage::class)->configuredDisk());
    }

    public function test_copy_chunk_moves_archivable_files_between_disks(): void
    {
        Storage::fake('nas');
        Storage::fake('r2_private');

        $media = Media::factory()->photo()->create(['original_storage' => Media::STORAGE_NAS]);

        Storage::disk('nas')->put($media->original_s3_key, 'ORIGINAL');
        Storage::disk('nas')->put($media->download_jpeg_s3_key, 'JPEG');
        Storage::disk('nas')->put($media->download_webp_s3_key, 'WEBP');

        $result = app(ArchiveStorage::class)->copyChunk([$media->id], 'nas', 'r2_private');

        $this->assertSame(3, $result['copied']);
        Storage::disk('r2_private')->assertExists($media->original_s3_key);
        Storage::disk('r2_private')->assertExists($media->download_jpeg_s3_key);

        // Idempotens: masodszorra kihagyja.
        $again = app(ArchiveStorage::class)->copyChunk([$media->id], 'nas', 'r2_private');
        $this->assertSame(0, $again['copied']);
        $this->assertSame(3, $again['skipped']);
    }

    public function test_start_sync_dispatches_a_batch_over_archived_media(): void
    {
        Bus::fake();
        $this->configureNas();
        $this->configureR2();

        Media::factory()->count(2)->photo()->create(['original_storage' => Media::STORAGE_NAS]);
        Media::factory()->photo()->create(['original_storage' => Media::STORAGE_LOCAL]);

        $this->actingAs(User::factory()->superadmin()->create())
            ->post('/admin/settings/storage/archive-sync', ['from' => 'nas', 'to' => 'r2_private'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Bus::assertBatched(fn ($batch) => $batch->name === ArchiveStorage::BATCH_NAME
            && $batch->jobs->every(fn ($job) => $job instanceof SyncArchiveMediaChunk));
    }

    public function test_sync_requires_two_different_configured_disks(): void
    {
        $this->configureNas();

        $this->actingAs(User::factory()->superadmin()->create())
            ->from('/admin/settings/storage')
            ->post('/admin/settings/storage/archive-sync', ['from' => 'nas', 'to' => 'nas'])
            ->assertSessionHasErrors('to');
    }
}
