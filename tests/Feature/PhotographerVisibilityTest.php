<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use App\Services\PhotographerVisibility;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function readyMediaAtEvent(User $photographer): array
    {
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE, 'slug' => 'teszt-kanyar']);
        $media = Media::factory()->create([
            'event_id' => $event->id,
            'photographer_id' => $photographer->id,
            'status' => Media::STATUS_READY,
        ]);

        return [$event, $media];
    }

    public function test_attribution_is_public_by_default(): void
    {
        $this->assertTrue(app(PhotographerVisibility::class)->attributionPublic());
    }

    public function test_gallery_media_carries_the_photographer_name_by_default(): void
    {
        $photographer = User::factory()->photographer()->create(['name' => 'Kovács Péter']);
        [$event] = $this->readyMediaAtEvent($photographer);

        $this->get("/events/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('media.data.0.photographer.name', 'Kovács Péter'));
    }

    public function test_superadmin_can_hide_the_photographer_attribution(): void
    {
        $photographer = User::factory()->photographer()->create(['name' => 'Kovács Péter']);
        [$event] = $this->readyMediaAtEvent($photographer);

        $this->actingAs(User::factory()->superadmin()->create())
            ->put('/admin/photographers/settings', ['attribution_public' => false])
            ->assertRedirect();

        $this->assertFalse(app(PhotographerVisibility::class)->attributionPublic());

        // Galéria: nincs fotós a média mellett
        $this->get("/events/{$event->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('photographerSearch', false)
                ->missing('media.data.0.photographer'));

        // Média-oldal: nincs fotós prop
        $media = Media::query()->first();
        $this->get("/media/{$media->id}")
            ->assertInertia(fn ($page) => $page->where('photographer', null)->where('contactGuard', fn ($g) => $g !== null));

        // A kereső fotós-listája üres
        $this->getJson('/api/photographers')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_hidden_attribution_ignores_the_photographer_search_filter(): void
    {
        $a = User::factory()->photographer()->create();
        $b = User::factory()->photographer()->create();
        [$eventA] = $this->readyMediaAtEvent($a);

        $eventB = Event::factory()->create(['status' => Event::STATUS_LIVE, 'slug' => 'masik-kanyar']);
        Media::factory()->create(['event_id' => $eventB->id, 'photographer_id' => $b->id, 'status' => Media::STATUS_READY]);

        app(PhotographerVisibility::class)->setAttributionPublic(false);

        // A crafted ?photographer_id= szűrőt a szerver figyelmen kívül hagyja → mindkét esemény jön
        $this->get("/events?photographer_id={$a->id}")
            ->assertInertia(fn ($page) => $page->where('events.data', fn ($list) => count($list) === 2));
    }

    public function test_attribution_toggle_is_superadmin_only(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/photographers/settings', ['attribution_public' => false])
            ->assertForbidden();

        $this->assertTrue(app(PhotographerVisibility::class)->attributionPublic());
    }
}
