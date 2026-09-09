<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeAndErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_homepage_exposes_live_stats(): void
    {
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE, 'location' => 'Mátraháza']);
        Media::factory()->photo()->count(3)->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);
        Media::factory()->video()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);
        Media::factory()->photo()->create(['event_id' => $event->id, 'status' => Media::STATUS_PROCESSING]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('stats.photos', 3)
            ->where('stats.videos', 1)
            ->where('stats.locations', 1));
    }

    public function test_unknown_route_renders_the_styled_error_page(): void
    {
        $this->get('/nem-letezik-ez-az-oldal')
            ->assertStatus(404)
            ->assertInertia(fn ($page) => $page
                ->component('Error')
                ->where('status', 404)
                // A fallback route megkapja a web middleware-t → van fordítás + locale.
                ->where('locale', 'hu')
                ->where('translations', fn ($t) => ($t['errors.404_title'] ?? null) === 'Nincs ilyen oldal'));
    }

    public function test_matched_route_404_still_gets_translations_and_branding(): void
    {
        // A route-model-binding a SubstituteBindings-ben bukik el (a HandleInertiaRequests
        // előtt) — a respond() handler kézzel pótolja a megosztott propokat.
        $this->get('/events/nincs-ilyen-esemeny')
            ->assertStatus(404)
            ->assertInertia(fn ($page) => $page
                ->component('Error')
                ->where('status', 404)
                ->where('translations', fn ($t) => ($t['errors.404_title'] ?? null) === 'Nincs ilyen oldal')
                ->has('branding'));
    }

    public function test_forbidden_area_renders_the_styled_error_page(): void
    {
        $photographer = User::factory()->photographer()->create();

        $this->actingAs($photographer)->get('/admin/photographers')
            ->assertStatus(403)
            ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));
    }

    public function test_robots_txt_allows_the_public_photographers_page(): void
    {
        $res = $this->get('/robots.txt');

        $res->assertOk();
        $this->assertStringNotContainsString("Disallow: /photographer\n", $res->getContent());
        $res->assertSee('Disallow: /photographer/', false);
    }
}
