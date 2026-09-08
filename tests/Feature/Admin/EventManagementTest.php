<?php

namespace Tests\Feature\Admin;

use App\Models\Country;
use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_an_event_with_auto_generated_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $country = Country::factory()->create();

        $response = $this->actingAs($admin)->post('/admin/events', [
            'country_id' => $country->id,
            'name' => 'Eger-kanyar',
            'location' => 'Eger',
            'latitude' => 47.9025,
            'longitude' => 20.3772,
            'event_date' => '2026-09-01',
            'starts_at' => '2026-09-01 09:00:00',
            'status' => 'draft',
        ]);

        $event = Event::sole();
        $response->assertRedirect("/admin/events/{$event->id}");
        $this->assertNotEmpty($event->slug);
        $this->assertSame($admin->id, $event->created_by);
    }

    public function test_photographer_can_create_an_own_event(): void
    {
        $photographer = User::factory()->photographer()->create();
        $country = Country::factory()->create();

        $response = $this->actingAs($photographer)->post('/admin/events', [
            'country_id' => $country->id,
            'name' => 'Saját esemény',
            'location' => 'Eger',
            'latitude' => 47.9,
            'longitude' => 20.3,
            'event_date' => '2026-09-01',
            'starts_at' => '2026-09-01 09:00:00',
            'status' => 'draft',
        ]);

        $event = Event::sole();
        $response->assertRedirect("/admin/events/{$event->id}");
        $this->assertSame($photographer->id, $event->created_by);
    }

    public function test_photographer_cannot_edit_or_delete_another_photographers_event(): void
    {
        $owner = User::factory()->photographer()->create();
        $intruder = User::factory()->photographer()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);

        $this->actingAs($intruder)->get("/admin/events/{$event->id}")->assertForbidden();
        $this->actingAs($intruder)->put("/admin/events/{$event->id}", ['name' => 'Hack'])->assertForbidden();
        $this->actingAs($intruder)->delete("/admin/events/{$event->id}")->assertForbidden();
    }

    public function test_photographer_can_edit_their_own_event(): void
    {
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create(['created_by' => $photographer->id, 'status' => Event::STATUS_DRAFT]);

        $response = $this->actingAs($photographer)->put("/admin/events/{$event->id}", [
            'country_id' => $event->country_id,
            'name' => $event->name,
            'location' => $event->location,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'event_date' => $event->event_date->toDateString(),
            'starts_at' => $event->starts_at->format('Y-m-d H:i:s'),
            'status' => Event::STATUS_LIVE,
        ]);

        $response->assertRedirect("/admin/events/{$event->id}");
        $this->assertSame(Event::STATUS_LIVE, $event->fresh()->status);
    }

    public function test_photographer_sees_events_with_their_media_and_events_they_created(): void
    {
        $photographer = User::factory()->photographer()->create();
        $otherPhotographer = User::factory()->photographer()->create();

        $ownMediaEvent = Event::factory()->create();
        Media::factory()->for($ownMediaEvent)->create(['photographer_id' => $photographer->id]);

        $ownCreatedEvent = Event::factory()->create(['created_by' => $photographer->id]);

        $foreignEvent = Event::factory()->create();
        Media::factory()->for($foreignEvent)->create(['photographer_id' => $otherPhotographer->id]);

        $response = $this->actingAs($photographer)->get('/admin/events');

        $response->assertOk();
        $ids = collect($response->viewData('page')['props']['events']['data'])->pluck('id');
        $this->assertTrue($ids->contains($ownMediaEvent->id));
        $this->assertTrue($ids->contains($ownCreatedEvent->id));
        $this->assertFalse($ids->contains($foreignEvent->id));
    }

    public function test_admin_can_filter_events_by_location_country_photographer_and_date(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();

        $hungary = Country::factory()->create(['name_hu' => 'Magyarország']);
        $austria = Country::factory()->create(['name_hu' => 'Ausztria']);

        $matra = Event::factory()->create([
            'name' => 'Mátra Rally', 'location' => 'Mátraháza',
            'country_id' => $hungary->id, 'event_date' => '2026-08-20',
        ]);
        Media::factory()->for($matra)->create(['photographer_id' => $photographer->id]);

        $glockner = Event::factory()->create([
            'name' => 'Glockner Sprint', 'location' => 'Grossglockner',
            'country_id' => $austria->id, 'event_date' => '2026-10-05',
        ]);

        $ids = fn ($response) => collect($response->viewData('page')['props']['events']['data'])->pluck('id');

        // Helyszín (szabad szöveg)
        $byLocation = $this->actingAs($admin)->get('/admin/events?search=Mátrah');
        $this->assertTrue($ids($byLocation)->contains($matra->id));
        $this->assertFalse($ids($byLocation)->contains($glockner->id));

        // Ország
        $byCountry = $this->actingAs($admin)->get("/admin/events?country_id={$austria->id}");
        $this->assertEqualsCanonicalizing([$glockner->id], $ids($byCountry)->all());

        // Fotós
        $byPhotographer = $this->actingAs($admin)->get("/admin/events?photographer_id={$photographer->id}");
        $this->assertEqualsCanonicalizing([$matra->id], $ids($byPhotographer)->all());

        // Dátum-tartomány
        $byDate = $this->actingAs($admin)->get('/admin/events?date_from=2026-09-01&date_to=2026-12-31');
        $this->assertEqualsCanonicalizing([$glockner->id], $ids($byDate)->all());
    }

    public function test_event_with_media_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();
        Media::factory()->for($event)->create();

        $response = $this->actingAs($admin)->delete("/admin/events/{$event->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_admin_can_update_an_event(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['status' => Event::STATUS_DRAFT]);

        $response = $this->actingAs($admin)->put("/admin/events/{$event->id}", [
            'country_id' => $event->country_id,
            'name' => $event->name,
            'location' => $event->location,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'event_date' => $event->event_date->toDateString(),
            'starts_at' => $event->starts_at->format('Y-m-d H:i:s'),
            'status' => Event::STATUS_LIVE,
        ]);

        $response->assertRedirect("/admin/events/{$event->id}");
        $this->assertSame(Event::STATUS_LIVE, $event->fresh()->status);
    }
}
