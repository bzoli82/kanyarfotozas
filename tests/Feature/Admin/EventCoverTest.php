<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_sets_and_clears_the_event_cover(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE]);
        $first = Media::factory()->photo()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'thumbnail_s3_key' => 'a.webp']);
        $chosen = Media::factory()->photo()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'thumbnail_s3_key' => 'b.webp']);

        // Alap: az első kész média.
        $cover = fn () => Event::query()->withCoverThumbnail()->whereKey($event->id)->first()->cover_thumbnail_s3_key;
        $this->assertSame('a.webp', $cover());

        $this->actingAs($admin)
            ->put("/admin/events/{$event->id}/cover", ['cover_media_id' => $chosen->id])
            ->assertRedirect();

        $this->assertSame($chosen->id, $event->fresh()->cover_media_id);
        $this->assertSame('b.webp', $cover());

        $this->actingAs($admin)->put("/admin/events/{$event->id}/cover", ['cover_media_id' => null]);
        $this->assertNull($event->fresh()->cover_media_id);
        $this->assertSame('a.webp', $cover());
    }

    public function test_cannot_set_a_media_from_another_event_as_cover(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();
        $otherMedia = Media::factory()->photo()->create(['event_id' => Event::factory()->create()->id, 'status' => Media::STATUS_READY]);

        $this->actingAs($admin)
            ->put("/admin/events/{$event->id}/cover", ['cover_media_id' => $otherMedia->id])
            ->assertSessionHasErrors('cover_media_id');
    }

    public function test_photographer_can_set_cover_on_their_own_event(): void
    {
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create(['created_by' => $photographer->id]);
        $media = Media::factory()->photo()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'photographer_id' => $photographer->id]);

        $this->actingAs($photographer)
            ->put("/admin/events/{$event->id}/cover", ['cover_media_id' => $media->id])
            ->assertRedirect();

        $this->assertSame($media->id, $event->fresh()->cover_media_id);
    }
}
