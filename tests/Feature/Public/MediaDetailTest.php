<?php

namespace Tests\Feature\Public;

use App\Models\Event;
use App\Models\Media;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_ready_media_detail_page_is_accessible(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY]);

        $response = $this->get("/media/{$media->id}");

        $response->assertOk();
        $this->assertSame($media->id, $response->viewData('page')['props']['media']['id']);
    }

    public function test_non_ready_media_detail_page_is_not_found(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);

        $this->get("/media/{$media->id}")->assertNotFound();
    }

    public function test_nearby_media_is_limited_to_same_event_within_time_window(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();

        $main = Media::factory()->create([
            'event_id' => $event->id,
            'status' => Media::STATUS_READY,
            'shot_at' => '2026-06-01 10:00:00',
        ]);

        $near = Media::factory()->create([
            'event_id' => $event->id,
            'status' => Media::STATUS_READY,
            'shot_at' => '2026-06-01 10:02:00',
        ]);

        Media::factory()->create([
            'event_id' => $event->id,
            'status' => Media::STATUS_READY,
            'shot_at' => '2026-06-01 10:10:00',
        ]);

        Media::factory()->create([
            'event_id' => $otherEvent->id,
            'status' => Media::STATUS_READY,
            'shot_at' => '2026-06-01 10:01:00',
        ]);

        $response = $this->get("/media/{$main->id}");

        $nearbyIds = collect($response->viewData('page')['props']['nearby'])->pluck('id');
        $this->assertSame([$near->id], $nearbyIds->all());
    }
}
