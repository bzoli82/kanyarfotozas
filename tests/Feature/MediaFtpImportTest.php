<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Event;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaFtpImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('nas');
    }

    private function configureFtp(): void
    {
        SiteSetting::set('nas_host', 'nas.example');
        SiteSetting::set('nas_username', 'importer');
        SiteSetting::set('nas_password', Crypt::encryptString('secret'));
    }

    private function putRemoteImage(string $path): void
    {
        // A szélességet az útvonalból származtatjuk, hogy minden fájl tartalma
        // egyedi legyen (a tartalom-alapú duplikátum-szűrés miatt).
        $width = 600 + (crc32($path) % 60);
        Storage::disk('nas')->put($path, UploadedFile::fake()->image(basename($path), $width, 480)->getContent());
    }

    public function test_browse_lists_subfolders_and_only_image_files(): void
    {
        $this->configureFtp();
        $this->putRemoteImage('rally-2026/kanyar1.jpg');
        $this->putRemoteImage('rally-2026/kanyar2.png');
        Storage::disk('nas')->put('rally-2026/jegyzet.txt', 'not an image');
        $this->putRemoteImage('archiv/regi.jpg');

        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $root = $this->actingAs($admin)->getJson('/admin/media-import/browse');
        $root->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('path', '')
            ->assertJsonCount(2, 'directories')
            ->assertJsonCount(0, 'files');

        $folder = $this->actingAs($admin)->getJson('/admin/media-import/browse?path=rally-2026');
        $folder->assertOk()
            ->assertJsonCount(2, 'files')
            ->assertJsonPath('files.0.name', 'kanyar1.jpg')
            ->assertJsonPath('segments.0.name', 'rally-2026');
    }

    public function test_browse_rejects_path_traversal(): void
    {
        $this->configureFtp();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/admin/media-import/browse?path='.urlencode('../../etc'))
            ->assertStatus(422);
    }

    public function test_import_downloads_selected_images_and_runs_the_pipeline(): void
    {
        $this->configureFtp();
        $this->putRemoteImage('rally/kanyar1.jpg');
        $this->putRemoteImage('rally/kanyar2.jpg');

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/import", [
                'photographer_id' => $photographer->id,
                'paths' => ['rally/kanyar1.jpg', 'rally/kanyar2.jpg'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('media', 2);

        $media = Media::query()->where('import_source_path', 'rally/kanyar1.jpg')->sole();
        $this->assertSame($photographer->id, $media->photographer_id);
        $this->assertSame($event->id, $media->event_id);
        $this->assertSame(Media::TYPE_PHOTO, $media->type);
        // A ProcessImageMedia job sync queue-n azonnal lefut a tesztekben.
        $this->assertSame(Media::STATUS_READY, $media->status);
        Storage::disk('public')->assertExists($media->thumbnail_s3_key);
        Storage::disk('public')->assertExists($media->watermarked_s3_key);
    }

    public function test_import_is_idempotent_per_source_path(): void
    {
        $this->configureFtp();
        $this->putRemoteImage('rally/kanyar1.jpg');

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $payload = ['photographer_id' => $photographer->id, 'paths' => ['rally/kanyar1.jpg']];

        $this->actingAs($admin)->post("/admin/events/{$event->id}/import", $payload)->assertRedirect();
        $this->actingAs($admin)->post("/admin/events/{$event->id}/import", $payload)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('media', 1);
    }

    public function test_browse_reports_when_the_ftp_connection_is_not_configured(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/admin/media-import/browse')
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    public function test_inactive_photographers_cannot_use_the_importer(): void
    {
        $this->configureFtp();
        $photographer = User::factory()->photographer()->create(['is_active' => false]);
        $event = Event::factory()->create(['created_by' => $photographer->id]);

        $this->actingAs($photographer)->getJson('/admin/media-import/browse')->assertForbidden();
        $this->actingAs($photographer)
            ->post("/admin/events/{$event->id}/import", ['paths' => ['x/y.jpg']])
            ->assertForbidden();
    }

    public function test_active_photographers_browse_only_their_own_scoped_folder(): void
    {
        $this->configureFtp();
        $anna = User::factory()->photographer()->create();
        $bela = User::factory()->photographer()->create();

        // Anna a saját mappájában lát, Béla mappájában nem.
        $this->putRemoteImage("fotosok/{$anna->id}/rally/a.jpg");
        $this->putRemoteImage("fotosok/{$bela->id}/rally/b.jpg");

        $anna->refresh();
        $listing = $this->actingAs($anna)->getJson('/admin/media-import/browse?path=rally')->assertOk();
        $listing->assertJsonCount(1, 'files')->assertJsonPath('files.0.name', 'a.jpg');

        // A visszaadott útvonal a scope-hoz relatív (nem szivárog ki a fotós-id).
        $this->assertSame('rally/a.jpg', $listing->json('files.0.path'));
    }

    public function test_new_event_can_import_ftp_images_on_creation(): void
    {
        $this->configureFtp();
        $this->putRemoteImage('rally/kanyar1.jpg');
        $this->putRemoteImage('rally/kanyar2.jpg');

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $country = Country::factory()->create();

        $this->actingAs($admin)
            ->post('/admin/events', [
                'country_id' => $country->id,
                'name' => 'Import Teszt Rally',
                'location' => 'Mátraháza',
                'latitude' => 47.85,
                'longitude' => 19.98,
                'event_date' => '2026-10-01',
                'starts_at' => '2026-10-01T08:00',
                'import_photographer_id' => $photographer->id,
                'import_paths' => ['rally/kanyar1.jpg', 'rally/kanyar2.jpg'],
                'status' => 'draft',
            ])
            ->assertRedirect();

        $event = Event::query()->where('name', 'Import Teszt Rally')->sole();
        $this->assertDatabaseCount('media', 2);
        $this->assertSame(2, Media::query()->where('event_id', $event->id)->where('photographer_id', $photographer->id)->count());
    }

    public function test_new_event_import_requires_a_photographer(): void
    {
        $this->configureFtp();
        $admin = User::factory()->admin()->create();
        $country = Country::factory()->create();

        $this->actingAs($admin)
            ->post('/admin/events', [
                'country_id' => $country->id,
                'name' => 'Hiányzó Fotós Rally',
                'location' => 'Eger',
                'latitude' => 47.9,
                'longitude' => 20.37,
                'event_date' => '2026-10-01',
                'starts_at' => '2026-10-01T08:00',
                'import_paths' => ['rally/kanyar1.jpg'],
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('import_photographer_id');

        $this->assertDatabaseCount('events', 0);
    }
}
