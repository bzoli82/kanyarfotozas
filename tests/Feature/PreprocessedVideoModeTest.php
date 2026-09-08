<?php

namespace Tests\Feature;

use App\Jobs\ArchiveMediaOriginalToNas;
use App\Jobs\ProcessVideoMedia;
use App\Models\Event;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\PreprocessedVideoGrouper;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreprocessedVideoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('nas');
        config(['media.video_mode' => 'preprocessed']);
    }

    private function videoUpload(string $name): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/sample.mp4'), $name, 'video/mp4', null, true);
    }

    public function test_grouper_pairs_master_lores_and_poster_by_stem(): void
    {
        $result = PreprocessedVideoGrouper::group([
            'klip.mp4' => 'M',
            'klip_lores.mp4' => 'L',
            'klip.jpg' => 'P',
            'onlyphoto.jpg' => 'X',
            'orphan_lores.mp4' => 'O',
        ]);

        $this->assertCount(1, $result['videos']);
        $this->assertSame(['M', 'L', 'P'], [
            $result['videos'][0]['master'],
            $result['videos'][0]['lores'],
            $result['videos'][0]['poster'],
        ]);
        $this->assertSame(['X'], $result['photos']);
        $this->assertSame(['orphan_lores'], $result['orphan_lores']);
    }

    public function test_web_upload_of_the_triple_creates_one_ready_video_without_ffmpeg(): void
    {
        Bus::fake([ProcessVideoMedia::class, ArchiveMediaOriginalToNas::class]);

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/media", [
                'photographer_id' => $photographer->id,
                'files' => [
                    $this->videoUpload('klip.mp4'),
                    $this->videoUpload('klip_lores.mp4'),
                    UploadedFile::fake()->image('klip.jpg', 640, 360),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Media::query()->where('event_id', $event->id)->count());

        $media = Media::query()->firstOrFail();
        $this->assertSame(Media::TYPE_VIDEO, $media->type);
        $this->assertSame(Media::STATUS_READY, $media->status);
        $this->assertNotNull($media->watermarked_s3_key);
        $this->assertNotNull($media->thumbnail_s3_key);
        $this->assertNull($media->preview_sprite_s3_key);
        $this->assertNull($media->hls_playlist_s3_key);

        Storage::disk('public')->assertExists($media->watermarked_s3_key);
        Storage::disk('public')->assertExists($media->thumbnail_s3_key);

        Bus::assertNotDispatched(ProcessVideoMedia::class);
        Bus::assertDispatched(ArchiveMediaOriginalToNas::class);
    }

    public function test_web_upload_video_without_lores_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/media", [
                'photographer_id' => $photographer->id,
                'files' => [$this->videoUpload('magary.mp4')],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Media::query()->count());
    }

    public function test_poster_is_optional(): void
    {
        Bus::fake();

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/media", [
                'photographer_id' => $photographer->id,
                'files' => [$this->videoUpload('klip.mp4'), $this->videoUpload('klip_lores.mp4')],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $media = Media::query()->firstOrFail();
        $this->assertNull($media->thumbnail_s3_key);
        $this->assertSame(Media::STATUS_READY, $media->status);
    }

    private function configureFtp(): void
    {
        SiteSetting::set('nas_host', 'nas.example');
        SiteSetting::set('nas_username', 'importer');
        SiteSetting::set('nas_password', Crypt::encryptString('secret'));
    }

    public function test_ftp_browser_shows_master_videos_but_hides_lores_siblings(): void
    {
        $this->configureFtp();
        Storage::disk('nas')->put('rally/klip.mp4', 'master');
        Storage::disk('nas')->put('rally/klip_lores.mp4', 'lores');
        Storage::disk('nas')->put('rally/foto.jpg', UploadedFile::fake()->image('foto.jpg')->getContent());

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/admin/media-import/browse?path=rally')
            ->assertOk()
            ->assertJsonCount(2, 'files')
            ->assertJsonPath('files.0.name', 'foto.jpg')
            ->assertJsonPath('files.1.name', 'klip.mp4');
    }

    public function test_ftp_import_pairs_lores_and_poster_siblings(): void
    {
        Bus::fake([ProcessVideoMedia::class, ArchiveMediaOriginalToNas::class]);
        $this->configureFtp();

        Storage::disk('nas')->put('rally/klip.mp4', file_get_contents(base_path('tests/Fixtures/sample.mp4')));
        Storage::disk('nas')->put('rally/klip_lores.mp4', 'low-res-preview-bytes');
        Storage::disk('nas')->put('rally/klip.jpg', UploadedFile::fake()->image('klip.jpg', 320, 180)->getContent());

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/import", [
                'photographer_id' => $photographer->id,
                'paths' => ['rally/klip.mp4'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $media = Media::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertSame(Media::TYPE_VIDEO, $media->type);
        $this->assertSame(Media::STATUS_READY, $media->status);
        $this->assertSame('rally/klip.mp4', $media->import_source_path);
        $this->assertNotNull($media->thumbnail_s3_key);
        Storage::disk('public')->assertExists($media->watermarked_s3_key);

        Bus::assertNotDispatched(ProcessVideoMedia::class);
    }
}
