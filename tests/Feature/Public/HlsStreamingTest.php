<?php

namespace Tests\Feature\Public;

use App\Models\Event;
use App\Models\Media;
use App\Services\VideoProcessingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HlsStreamingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_make_hls_stream_produces_a_master_and_variant_playlists_with_segments(): void
    {
        $outputDir = storage_path('app/tmp/hls-test-'.uniqid());

        try {
            $result = app(VideoProcessingService::class)->makeHlsStream(
                base_path('tests/Fixtures/sample.mp4'),
                320,
                240,
                $outputDir,
            );

            $this->assertSame('master.m3u8', $result['master']);
            $this->assertFileExists("{$outputDir}/master.m3u8");

            $master = file_get_contents("{$outputDir}/master.m3u8");
            $this->assertStringContainsString('#EXTM3U', $master);
            $this->assertStringContainsString('#EXT-X-STREAM-INF:BANDWIDTH=', $master);

            $variantPlaylists = collect($result['files'])->filter(fn ($f) => str_ends_with($f, 'p.m3u8'));
            $this->assertNotEmpty($variantPlaylists);

            $segments = collect($result['files'])->filter(fn ($f) => str_ends_with($f, '.ts'));
            $this->assertNotEmpty($segments);
            $segments->each(fn ($s) => $this->assertFileExists("{$outputDir}/{$s}"));
        } finally {
            File::deleteDirectory($outputDir);
        }
    }

    public function test_media_resource_exposes_the_hls_playlist_key(): void
    {
        $event = Event::factory()->create(['name' => 'HLS Kanyar', 'location' => 'HLSfalu', 'status' => Event::STATUS_LIVE]);
        Media::factory()->video()->create([
            'event_id' => $event->id,
            'status' => Media::STATUS_READY,
            'hls_playlist_s3_key' => 'hls/42/master.m3u8',
        ]);

        $data = $this->getJson("/api/events/{$event->id}/media")->json('data.0');

        $this->assertSame('hls/42/master.m3u8', $data['hls_playlist_s3_key']);
    }
}
