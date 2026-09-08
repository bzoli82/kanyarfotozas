<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ArchiveMediaOriginalToNas;
use App\Jobs\ProcessVideoMedia;
use App\Models\Media;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessVideoMediaTest extends TestCase
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

    private function putFixture(string $fixture, string $key): void
    {
        Storage::disk('local')->put($key, file_get_contents(base_path("tests/Fixtures/{$fixture}")));
    }

    public function test_it_processes_an_mp4_video_and_marks_ready(): void
    {
        Bus::fake([ArchiveMediaOriginalToNas::class]);

        $this->putFixture('sample.mp4', 'originals/1/sample.mp4');

        $media = Media::factory()->create([
            'type' => Media::TYPE_VIDEO,
            'status' => Media::STATUS_PROCESSING,
            'original_s3_key' => 'originals/1/sample.mp4',
            'thumbnail_s3_key' => null,
            'watermarked_s3_key' => null,
            'preview_sprite_s3_key' => null,
        ]);

        app()->call([new ProcessVideoMedia($media->id), 'handle']);

        $media->refresh();

        $this->assertSame(Media::STATUS_READY, $media->status);
        $this->assertSame(320, $media->width);
        $this->assertSame(240, $media->height);
        $this->assertGreaterThanOrEqual(5, $media->duration_seconds);
        $this->assertSame('originals/1/sample.mp4', $media->original_s3_key);

        Storage::disk('public')->assertExists($media->thumbnail_s3_key);
        Storage::disk('public')->assertExists($media->watermarked_s3_key);
        Storage::disk('public')->assertExists($media->preview_sprite_s3_key);
        $this->assertSame(2, $media->preview_sprite_interval);

        // HLS adaptiv stream (EPIC-16): master + legalabb egy variant + szegmensek
        $this->assertSame("hls/{$media->id}/master.m3u8", $media->hls_playlist_s3_key);
        Storage::disk('public')->assertExists($media->hls_playlist_s3_key);
        $master = Storage::disk('public')->get($media->hls_playlist_s3_key);
        $this->assertStringContainsString('#EXT-X-STREAM-INF', $master);
        $this->assertNotEmpty(Storage::disk('public')->files("hls/{$media->id}"));
        $this->assertTrue(
            collect(Storage::disk('public')->files("hls/{$media->id}"))->contains(fn ($f) => str_ends_with($f, '.ts'))
        );

        Bus::assertDispatched(ArchiveMediaOriginalToNas::class, fn ($job) => $job->mediaId === $media->id);
    }

    public function test_it_remuxes_non_mp4_originals_to_mp4(): void
    {
        $this->putFixture('sample.mov', 'originals/1/sample.mov');

        $media = Media::factory()->create([
            'type' => Media::TYPE_VIDEO,
            'status' => Media::STATUS_PROCESSING,
            'original_s3_key' => 'originals/1/sample.mov',
        ]);

        app()->call([new ProcessVideoMedia($media->id), 'handle']);

        $media->refresh();

        // A NAS-archivalas is lefut sync queue-n a tesztekben, ezert az original_s3_key
        // mar a NAS-utvonalra mutat — csak azt ellenorizzuk, hogy .mp4-re valtott at
        // (nem a regi .mov-ra), es a lokalis .mov fajl eltunt.
        $this->assertStringEndsWith('.mp4', $media->original_s3_key);
        Storage::disk('local')->assertMissing('originals/1/sample.mov');
        Storage::disk('local')->assertMissing('originals/1/sample.mp4');
        Storage::disk('nas')->assertExists($media->original_s3_key);
    }

    public function test_it_ignores_photo_media(): void
    {
        $media = Media::factory()->create([
            'type' => Media::TYPE_PHOTO,
            'status' => Media::STATUS_PROCESSING,
            'thumbnail_s3_key' => null,
        ]);

        app()->call([new ProcessVideoMedia($media->id), 'handle']);

        $this->assertNull($media->fresh()->thumbnail_s3_key);
    }

    public function test_it_marks_media_as_failed_after_final_attempt(): void
    {
        $media = Media::factory()->create(['type' => Media::TYPE_VIDEO, 'status' => Media::STATUS_PROCESSING]);

        (new ProcessVideoMedia($media->id))->failed(new \RuntimeException('boom'));

        $this->assertSame(Media::STATUS_FAILED, $media->fresh()->status);
    }
}
