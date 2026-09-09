<?php

namespace Tests\Feature\Public;

use App\Models\Country;
use App\Models\Event;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * A GPS sugaras keresés PostGIS-t igényel (ST_DWithin). Az éles cél PostGIS
     * nélkül fut (a funkció alapból KI), a CI is sima Postgres-en — ott ezek a
     * tesztek kimaradnak. Lokálisan (PostGIS-es tesztadatbázis) lefutnak.
     */
    private function requirePostgis(): void
    {
        if (! DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'postgis'")) {
            $this->markTestSkipped('PostGIS nem elérhető — a GPS sugaras keresés tesztje kimarad.');
        }
    }

    public function test_events_index_lists_live_and_announced_events(): void
    {
        $live = Event::factory()->create(['status' => Event::STATUS_LIVE, 'name' => 'Eger Kanyar']);
        $announced = Event::factory()->create(['status' => Event::STATUS_ANNOUNCED, 'name' => 'Jövő Kanyar']);
        $draft = Event::factory()->create(['status' => Event::STATUS_DRAFT, 'name' => 'Titkos Vázlat']);

        $response = $this->get('/events');

        $response->assertOk();
        $ids = collect($response->viewData('page')['props']['events']['data'])->pluck('id');
        $this->assertTrue($ids->contains($live->id));
        $this->assertTrue($ids->contains($announced->id));
        $this->assertFalse($ids->contains($draft->id));
    }

    public function test_events_index_filters_by_location(): void
    {
        $eger = Event::factory()->create(['status' => Event::STATUS_LIVE, 'name' => 'Eger Kanyar', 'location' => 'Eger']);
        $matra = Event::factory()->create(['status' => Event::STATUS_LIVE, 'name' => 'Mátra Rally', 'location' => 'Mátraháza']);

        $response = $this->get('/events?location=Eger');

        $ids = collect($response->viewData('page')['props']['events']['data'])->pluck('id');
        $this->assertTrue($ids->contains($eger->id));
        $this->assertFalse($ids->contains($matra->id));
    }

    public function test_draft_event_gallery_is_not_publicly_accessible(): void
    {
        $event = Event::factory()->create(['status' => Event::STATUS_DRAFT]);

        $this->get("/events/{$event->slug}")->assertNotFound();
    }

    public function test_gallery_only_shows_ready_media_and_hides_others(): void
    {
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE]);
        $ready = Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);
        Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_PROCESSING]);
        Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_HIDDEN]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk();
        $ids = collect($response->viewData('page')['props']['media']['data'])->pluck('id');
        $this->assertSame([$ready->id], $ids->all());
    }

    public function test_gallery_media_includes_photographer_name_for_the_lightbox(): void
    {
        SiteSetting::set('photographer_attribution_public', '1');
        $photographer = User::factory()->photographer()->create(['name' => 'Teszt Fotós']);
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE]);
        Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'photographer_id' => $photographer->id]);

        $response = $this->get("/events/{$event->slug}");

        $this->assertSame('Teszt Fotós', $response->viewData('page')['props']['media']['data'][0]['photographer']['name']);
    }

    public function test_gallery_can_be_filtered_by_media_type(): void
    {
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE]);
        $photo = Media::factory()->photo()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);
        Media::factory()->video()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);

        $response = $this->get("/events/{$event->slug}?type=photo");

        $ids = collect($response->viewData('page')['props']['media']['data'])->pluck('id');
        $this->assertSame([$photo->id], $ids->all());
    }

    public function test_api_events_media_endpoint_paginates_for_infinite_scroll(): void
    {
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE]);
        Media::factory()->count(30)->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);

        $page1 = $this->getJson("/api/events/{$event->id}/media");
        $page1->assertOk();
        $this->assertCount(24, $page1->json('data'));
        $this->assertNotNull($page1->json('next_page_url'));

        $page2 = $this->getJson("/api/events/{$event->id}/media?page=2");
        $this->assertCount(6, $page2->json('data'));
        $this->assertNull($page2->json('next_page_url'));
    }

    public function test_country_filter_narrows_events(): void
    {
        $hu = Country::factory()->create(['code' => 'HU']);
        $at = Country::factory()->create(['code' => 'AT']);
        $huEvent = Event::factory()->create(['status' => Event::STATUS_LIVE, 'country_id' => $hu->id]);
        $atEvent = Event::factory()->create(['status' => Event::STATUS_LIVE, 'country_id' => $at->id]);

        $response = $this->get('/events?'.http_build_query(['countries' => ['HU']]));

        $ids = collect($response->viewData('page')['props']['events']['data'])->pluck('id');
        $this->assertTrue($ids->contains($huEvent->id));
        $this->assertFalse($ids->contains($atEvent->id));
    }

    public function test_gps_radius_search_includes_nearby_and_excludes_far_events_with_distance(): void
    {
        $this->requirePostgis();
        SiteSetting::set('geo_search_enabled', '1');

        // Eger kozeppontja korul: a "kozeli" esemeny kb. 5 km-re van (meg ugyanaz a varos),
        // a "tavoli" esemeny Budapesten (~110 km), ami 50 km-es sugaron kivul esik.
        $nearby = Event::factory()->create([
            'status' => Event::STATUS_LIVE, 'name' => 'Eger Kanyar', 'location' => 'Eger',
            'latitude' => 47.95, 'longitude' => 20.38,
        ]);
        $farAway = Event::factory()->create([
            'status' => Event::STATUS_LIVE, 'name' => 'Budapest Kanyar', 'location' => 'Budapest',
            'latitude' => 47.4979, 'longitude' => 19.0402,
        ]);

        $response = $this->get('/events?'.http_build_query(['lat' => 47.9025, 'lon' => 20.3772, 'radius' => 50]));

        $response->assertOk();
        $events = collect($response->viewData('page')['props']['events']['data']);
        $ids = $events->pluck('id');
        $this->assertTrue($ids->contains($nearby->id));
        $this->assertFalse($ids->contains($farAway->id));

        $nearbyRow = $events->firstWhere('id', $nearby->id);
        $this->assertArrayHasKey('distance_km', $nearbyRow);
        $this->assertLessThan(10, (float) $nearbyRow['distance_km']);
    }

    public function test_gps_radius_params_are_ignored_when_geo_search_is_disabled(): void
    {
        // Alapból KI van kapcsolva (PostGIS-függőség) — a lat/lon paramétereket
        // figyelmen kívül kell hagyni, nem szabad ST_DWithin-t hívni.
        $near = Event::factory()->create([
            'status' => Event::STATUS_LIVE, 'name' => 'Eger Kanyar', 'location' => 'Eger',
            'latitude' => 47.95, 'longitude' => 20.38,
        ]);
        $far = Event::factory()->create([
            'status' => Event::STATUS_LIVE, 'name' => 'Budapest Kanyar', 'location' => 'Budapest',
            'latitude' => 47.4979, 'longitude' => 19.0402,
        ]);

        $response = $this->get('/events?'.http_build_query(['lat' => 47.9025, 'lon' => 20.3772, 'radius' => 5]));

        $response->assertOk();
        $events = collect($response->viewData('page')['props']['events']['data']);
        $ids = $events->pluck('id');
        $this->assertTrue($ids->contains($near->id));
        $this->assertTrue($ids->contains($far->id), 'A távoli esemény is jön, mert a sugaras szűrés ki van kapcsolva.');
        $this->assertArrayNotHasKey('distance_km', $events->firstWhere('id', $near->id));
    }

    public function test_event_list_exposes_first_ready_media_thumbnail_as_cover(): void
    {
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE]);
        Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_PROCESSING, 'thumbnail_s3_key' => 'thumbnails/processing.webp']);
        $ready = Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'thumbnail_s3_key' => 'thumbnails/ready.webp']);

        $response = $this->get('/events');

        $row = collect($response->viewData('page')['props']['events']['data'])->firstWhere('id', $event->id);
        $this->assertSame('thumbnails/ready.webp', $row['cover_thumbnail_s3_key']);
    }

    public function test_event_without_ready_media_has_null_cover(): void
    {
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE]);

        $response = $this->get('/events');

        $row = collect($response->viewData('page')['props']['events']['data'])->firstWhere('id', $event->id);
        $this->assertNull($row['cover_thumbnail_s3_key']);
    }

    public function test_gps_radius_search_orders_results_by_distance_ascending(): void
    {
        $this->requirePostgis();
        SiteSetting::set('geo_search_enabled', '1');

        $far = Event::factory()->create([
            'status' => Event::STATUS_LIVE, 'name' => 'Mátra Rally', 'location' => 'Mátraháza',
            'latitude' => 47.87, 'longitude' => 19.95,
        ]);
        $near = Event::factory()->create([
            'status' => Event::STATUS_LIVE, 'name' => 'Eger Kanyar', 'location' => 'Eger',
            'latitude' => 47.91, 'longitude' => 20.38,
        ]);

        $response = $this->get('/events?'.http_build_query(['lat' => 47.9025, 'lon' => 20.3772, 'radius' => 50]));

        $ids = collect($response->viewData('page')['props']['events']['data'])->pluck('id')->values();
        $this->assertSame([$near->id, $far->id], $ids->all());
    }

    public function test_events_index_filters_by_photographer(): void
    {
        SiteSetting::set('photographer_attribution_public', '1');
        $anna = User::factory()->photographer()->create(['name' => 'Anna Fotós']);
        $bela = User::factory()->photographer()->create(['name' => 'Béla Fotós']);

        $annaEvent = Event::factory()->create(['status' => Event::STATUS_LIVE, 'name' => 'Anna Kanyar', 'location' => 'Anna hely']);
        $belaEvent = Event::factory()->create(['status' => Event::STATUS_LIVE, 'name' => 'Béla Kanyar', 'location' => 'Béla hely']);
        Media::factory()->photo()->create(['event_id' => $annaEvent->id, 'status' => Media::STATUS_READY, 'photographer_id' => $anna->id]);
        Media::factory()->photo()->create(['event_id' => $belaEvent->id, 'status' => Media::STATUS_READY, 'photographer_id' => $bela->id]);

        $response = $this->get('/events?photographer_id='.$anna->id);

        $ids = collect($response->viewData('page')['props']['events']['data'])->pluck('id');
        $this->assertTrue($ids->contains($annaEvent->id));
        $this->assertFalse($ids->contains($belaEvent->id));
    }

    public function test_events_index_combines_location_and_photographer_filters(): void
    {
        $anna = User::factory()->photographer()->create(['name' => 'Anna Fotós']);
        $bela = User::factory()->photographer()->create(['name' => 'Béla Fotós']);

        // Ugyanaz a helyszín, két külön esemény, két külön fotós — a térkép-modalból
        // egy pinre kattintva a fotós-szűrésnek is érvényesülnie kell.
        SiteSetting::set('photographer_attribution_public', '1');
        $annaEvent = Event::factory()->create(['status' => Event::STATUS_LIVE, 'name' => 'Sopron Anna', 'location' => 'Sopron']);
        $belaEvent = Event::factory()->create(['status' => Event::STATUS_LIVE, 'name' => 'Sopron Béla', 'location' => 'Sopron']);
        Media::factory()->photo()->create(['event_id' => $annaEvent->id, 'status' => Media::STATUS_READY, 'photographer_id' => $anna->id]);
        Media::factory()->photo()->create(['event_id' => $belaEvent->id, 'status' => Media::STATUS_READY, 'photographer_id' => $bela->id]);

        $response = $this->get('/events?location=Sopron&photographer_id='.$anna->id);

        $ids = collect($response->viewData('page')['props']['events']['data'])->pluck('id');
        $this->assertTrue($ids->contains($annaEvent->id));
        $this->assertFalse($ids->contains($belaEvent->id));
    }

    public function test_events_index_respects_per_page_selection(): void
    {
        Event::factory()->count(15)->create(['status' => Event::STATUS_LIVE]);

        $default = $this->get('/events');
        $this->assertCount(15, $default->viewData('page')['props']['events']['data']);
        $this->assertSame(20, $default->viewData('page')['props']['perPage']);

        $limited = $this->get('/events?per_page=10');
        $this->assertCount(10, $limited->viewData('page')['props']['events']['data']);
        $this->assertSame(10, $limited->viewData('page')['props']['perPage']);

        $invalid = $this->get('/events?per_page=7');
        $this->assertSame(20, $invalid->viewData('page')['props']['perPage']);
    }

    public function test_api_events_filters_markers_by_photographer_and_type(): void
    {
        SiteSetting::set('photographer_attribution_public', '1');
        $anna = User::factory()->photographer()->create(['name' => 'Anna Fotós']);
        $bela = User::factory()->photographer()->create(['name' => 'Béla Fotós']);

        $event = Event::factory()->create(['status' => Event::STATUS_LIVE, 'name' => 'Vegyes Kanyar', 'location' => 'Vegyes hely']);
        Media::factory()->photo()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'photographer_id' => $anna->id]);
        Media::factory()->video()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY, 'photographer_id' => $bela->id]);

        $belaOnly = Event::factory()->create(['status' => Event::STATUS_LIVE, 'name' => 'Béla Kanyar', 'location' => 'Béla hely']);
        Media::factory()->video()->create(['event_id' => $belaOnly->id, 'status' => Media::STATUS_READY, 'photographer_id' => $bela->id]);

        $response = $this->getJson('/api/events?photographer_id='.$anna->id.'&type=photo');

        $data = collect($response->json('data'));
        $this->assertTrue($data->pluck('id')->contains($event->id));
        $this->assertFalse($data->pluck('id')->contains($belaOnly->id));
        $this->assertSame(1, $data->firstWhere('id', $event->id)['media_count']);
    }
}
