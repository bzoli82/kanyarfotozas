<?php

namespace Tests\Feature\Public;

use App\Models\Event;
use App\Models\Media;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryTimeNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function event(): Event
    {
        return Event::factory()->create([
            'name' => 'Idő Kanyar',
            'location' => 'Időfalu',
            'status' => Event::STATUS_LIVE,
        ]);
    }

    public function test_histogram_returns_hourly_counts(): void
    {
        $event = $this->event();
        Media::factory()->count(3)->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'shot_at' => today()->setTime(9, 15)]);
        Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'shot_at' => today()->setTime(11, 40)]);

        $data = $this->getJson("/api/events/{$event->id}/media/histogram")->json('data');

        $this->assertSame(['hour' => 9, 'count' => 3], collect($data)->firstWhere('hour', 9));
        $this->assertSame(['hour' => 11, 'count' => 1], collect($data)->firstWhere('hour', 11));
    }

    public function test_nearby_returns_media_within_the_time_window(): void
    {
        $event = $this->event();
        $ref = Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'shot_at' => today()->setTime(10, 0)]);
        $close = Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'shot_at' => today()->setTime(10, 2)]);
        $far = Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'shot_at' => today()->setTime(10, 30)]);

        $ids = collect($this->getJson("/api/events/{$event->id}/media/nearby?media_id={$ref->id}&window_minutes=3")->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($close->id));
        $this->assertFalse($ids->contains($far->id));
        $this->assertFalse($ids->contains($ref->id));
    }

    public function test_gallery_filters_by_shot_time_window(): void
    {
        $event = $this->event();
        Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'shot_at' => today()->setTime(9, 45)]);
        Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'shot_at' => today()->setTime(14, 0)]);

        $props = $this->get("/events/{$event->slug}?shot_from=09:43&shot_to=09:47")->viewData('page')['props'];

        $this->assertCount(1, $props['media']['data']);
    }
}
