<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobilePwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_service_worker_is_built_at_the_public_root_for_root_scope(): void
    {
        $swPath = public_path('sw.js');
        if (! is_file($swPath)) {
            $this->markTestSkipped('sw.js még nincs buildelve (futtasd: npm run build).');
        }

        $sw = file_get_contents($swPath);
        // A precache URL-ek a /build/ alá mutatnak (modifyURLPrefix), a workbox
        // párja pedig a gyökérben van (./workbox-*).
        $this->assertStringContainsString('/build/assets/', $sw);
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertNotEmpty(glob(public_path('workbox-*.js')));
    }

    public function test_the_pwa_manifest_is_available_and_installable(): void
    {
        $manifestPath = public_path('manifest.webmanifest');
        if (! is_file($manifestPath)) {
            $this->markTestSkipped('manifest még nincs buildelve.');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/upload', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertNotEmpty(collect($manifest['icons'])->firstWhere('purpose', 'maskable'));
    }

    public function test_upload_page_requires_an_upload_capable_role(): void
    {
        $this->get('/upload')->assertRedirect('/login');

        // Nincs "customer" szerep — a latogato nem tud bejelentkezni; a role-middleware
        // amugy is csak superadmin|admin|photographer-t enged.
    }

    public function test_photographer_sees_only_their_own_events_on_the_upload_page(): void
    {
        $photographer = User::factory()->photographer()->create();
        $other = User::factory()->photographer()->create();

        $mine = Event::factory()->create(['name' => 'Sajat Kanyar', 'location' => 'A', 'created_by' => $photographer->id, 'status' => Event::STATUS_LIVE]);
        $withMyMedia = Event::factory()->create(['name' => 'Van Mediam', 'location' => 'B', 'status' => Event::STATUS_LIVE]);
        Media::factory()->create(['event_id' => $withMyMedia->id, 'photographer_id' => $photographer->id]);
        Event::factory()->create(['name' => 'Idegen Kanyar', 'location' => 'C', 'created_by' => $other->id, 'status' => Event::STATUS_LIVE]);

        $props = $this->actingAs($photographer)->get('/upload')->viewData('page')['props'];

        $names = collect($props['events'])->pluck('name');
        $this->assertTrue($names->contains('Sajat Kanyar'));
        $this->assertTrue($names->contains('Van Mediam'));
        $this->assertFalse($names->contains('Idegen Kanyar'));
        $this->assertFalse($props['isAdmin']);
    }

    public function test_admin_gets_the_photographer_list(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->photographer()->create(['name' => 'Feltölő Fotós']);

        $props = $this->actingAs($admin)->get('/upload')->viewData('page')['props'];

        $this->assertTrue($props['isAdmin']);
        $this->assertSame('Feltölő Fotós', collect($props['photographers'])->first()['name']);
    }
}
