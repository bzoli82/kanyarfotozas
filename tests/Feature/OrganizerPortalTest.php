<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Event;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use App\Services\OrganizerRevenue;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function paidSale(Event $event, int $price): void
    {
        $media = Media::factory()->for($event)->create(['status' => Media::STATUS_READY, 'price_cents' => $price]);
        $order = Order::factory()->paid()->create();
        $order->media()->attach($media->id, ['price_cents' => $price]);
    }

    public function test_organizer_sees_their_events_and_computed_share(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id, 'organizer_share_percent' => 20]);
        $foreign = Event::factory()->create();

        $this->paidSale($event, 5000);
        $this->paidSale($event, 5000);
        $this->paidSale($foreign, 9000);

        $this->actingAs($organizer)
            ->get('/organizer/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Organizer/Dashboard')
                ->where('totals.gross_cents', 10000)
                ->where('totals.share_cents', 2000)
                ->where('totals.outstanding_cents', 2000)
                ->where('events', fn ($events) => count($events) === 1 && $events[0]['id'] === $event->id));
    }

    public function test_organizer_cannot_open_another_organizers_event(): void
    {
        $mine = User::factory()->organizer()->create();
        $other = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $other->id, 'organizer_share_percent' => 10]);

        $this->actingAs($mine)->get("/organizer/events/{$event->id}")->assertForbidden();
    }

    public function test_photographer_cannot_reach_the_organizer_portal(): void
    {
        $photographer = User::factory()->photographer()->create();
        $this->actingAs($photographer)->get('/organizer/dashboard')->assertForbidden();
    }

    public function test_admin_assigns_an_organizer_and_share_to_an_event(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = User::factory()->organizer()->create();
        $country = Country::factory()->create();

        $this->actingAs($admin)->post('/admin/events', [
            'country_id' => $country->id,
            'name' => 'Szervezett Rally',
            'location' => 'Eger',
            'latitude' => 47.9,
            'longitude' => 20.37,
            'event_date' => '2026-11-01',
            'starts_at' => '2026-11-01T08:00',
            'status' => 'draft',
            'organizer_id' => $organizer->id,
            'organizer_share_percent' => 15,
        ])->assertRedirect();

        $event = Event::query()->where('name', 'Szervezett Rally')->sole();
        $this->assertSame($organizer->id, $event->organizer_id);
        $this->assertSame(15, $event->organizer_share_percent);
    }

    public function test_share_requires_a_percent_when_an_organizer_is_set(): void
    {
        $admin = User::factory()->admin()->create();
        $organizer = User::factory()->organizer()->create();
        $country = Country::factory()->create();

        $this->actingAs($admin)->post('/admin/events', [
            'country_id' => $country->id,
            'name' => 'Hiba Rally',
            'location' => 'Eger',
            'latitude' => 47.9,
            'longitude' => 20.37,
            'event_date' => '2026-11-01',
            'starts_at' => '2026-11-01T08:00',
            'status' => 'draft',
            'organizer_id' => $organizer->id,
        ])->assertSessionHasErrors('organizer_share_percent');
    }

    public function test_admin_can_record_an_organizer_payout_reducing_the_outstanding(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id, 'organizer_share_percent' => 50]);
        $this->paidSale($event, 10000); // share = 5000

        $this->actingAs($superadmin)->post('/admin/organizer-payouts', [
            'organizer_id' => $organizer->id,
            'event_id' => $event->id,
            'amount_cents' => 3000,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('organizer_payouts', ['organizer_id' => $organizer->id, 'amount_cents' => 3000]);
        $this->assertSame(2000, app(OrganizerRevenue::class)->totals($organizer->fresh())['outstanding_cents']);
    }

    public function test_organizer_payout_rejects_an_event_of_another_organizer(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $organizer = User::factory()->organizer()->create();
        $foreignEvent = Event::factory()->create();

        $this->actingAs($superadmin)->post('/admin/organizer-payouts', [
            'organizer_id' => $organizer->id,
            'event_id' => $foreignEvent->id,
            'amount_cents' => 1000,
        ])->assertStatus(422);
    }

    public function test_organizer_role_user_lands_on_the_organizer_dashboard_after_login(): void
    {
        $organizer = User::factory()->organizer()->create(['password' => bcrypt('secret-pass-123')]);

        $this->post('/login', ['email' => $organizer->email, 'password' => 'secret-pass-123'])
            ->assertRedirect('/organizer/dashboard');
    }
}
