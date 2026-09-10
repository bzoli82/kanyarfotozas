<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_photographer_cannot_view_the_activity_log(): void
    {
        $this->actingAs(User::factory()->photographer()->create())
            ->get('/admin/activity')
            ->assertForbidden();
    }

    public function test_admin_sees_logged_activity_with_causer_and_subject(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Teszt Admin']);

        // A LogsActivity modell egy módosítása causer-rel + subjecttel naplózódik.
        $event = Event::factory()->create(['status' => Event::STATUS_LIVE]);
        $this->actingAs($admin);
        $event->update(['location' => 'Hungaroring']);

        $props = $this->get('/admin/activity')->assertOk()->viewData('page')['props'];

        $this->assertNotEmpty($props['activity']['data']);
        $row = $props['activity']['data'][0];
        $this->assertSame('Teszt Admin', $row['causer']['name']);
        $this->assertSame('Event', $row['subject_type']);
        $this->assertSame("/admin/events/{$event->id}", $row['subject_link']);
    }

    public function test_activity_can_be_filtered_by_causer(): void
    {
        $a = User::factory()->admin()->create(['name' => 'Alfa']);
        $b = User::factory()->admin()->create(['name' => 'Béta']);

        $this->actingAs($a);
        Event::factory()->create();
        $this->actingAs($b);
        Event::factory()->create();

        $props = $this->actingAs($a)->get('/admin/activity?causer='.$a->id)->viewData('page')['props'];

        $names = collect($props['activity']['data'])->pluck('causer.name')->unique();
        $this->assertSame(['Alfa'], $names->values()->all());
    }
}
