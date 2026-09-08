<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Event;
use App\Models\Media;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaDedupAndPricingTest extends TestCase
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

    /** Ugyanabból a bájtsorozatból tetszőleges nevű feltöltendő fájlt gyárt. */
    private function uploadFrom(string $bytes, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'kf').'.jpg';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private function imageBytes(): string
    {
        return UploadedFile::fake()->image('seed.jpg', 240, 180)->getContent();
    }

    public function test_duplicate_file_upload_is_skipped_within_an_event(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();
        $bytes = $this->imageBytes();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/media", [
                'photographer_id' => $photographer->id,
                'files' => [$this->uploadFrom($bytes, 'elso.jpg'), $this->uploadFrom($bytes, 'masodik.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Media::query()->where('event_id', $event->id)->count());
    }

    public function test_the_same_file_can_be_uploaded_to_a_different_event(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $eventA = Event::factory()->create();
        $eventB = Event::factory()->create();
        $bytes = $this->imageBytes();

        foreach ([$eventA, $eventB] as $event) {
            $this->actingAs($admin)->post("/admin/events/{$event->id}/media", [
                'photographer_id' => $photographer->id,
                'files' => [$this->uploadFrom($bytes, 'kanyar.jpg')],
            ])->assertRedirect();
        }

        $this->assertSame(1, Media::query()->where('event_id', $eventA->id)->count());
        $this->assertSame(1, Media::query()->where('event_id', $eventB->id)->count());
    }

    public function test_ftp_import_skips_files_with_identical_content(): void
    {
        SiteSetting::set('nas_host', 'nas.example');
        SiteSetting::set('nas_username', 'importer');
        SiteSetting::set('nas_password', Crypt::encryptString('secret'));

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $bytes = $this->imageBytes();
        Storage::disk('nas')->put('mappa-a/kep.jpg', $bytes);
        Storage::disk('nas')->put('mappa-b/ugyanaz.jpg', $bytes);

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/import", [
                'photographer_id' => $photographer->id,
                'paths' => ['mappa-a/kep.jpg', 'mappa-b/ugyanaz.jpg'],
            ])
            ->assertRedirect();

        $this->assertSame(1, Media::query()->where('event_id', $event->id)->count());
    }

    public function test_new_media_inherits_the_event_photo_and_video_price(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create(['photo_price_cents' => 3000, 'video_price_cents' => 5500]);

        $this->actingAs($admin)->post("/admin/events/{$event->id}/media", [
            'photographer_id' => $photographer->id,
            'files' => [
                UploadedFile::fake()->image('foto.jpg', 200, 150),
                new UploadedFile(base_path('tests/Fixtures/sample.mp4'), 'klip.mp4', 'video/mp4', null, true),
            ],
        ])->assertRedirect();

        $this->assertSame(3000, Media::query()->where('event_id', $event->id)->where('type', 'photo')->value('price_cents'));
        $this->assertSame(5500, Media::query()->where('event_id', $event->id)->where('type', 'video')->value('price_cents'));
    }

    public function test_changing_the_event_price_reprices_existing_unsold_media(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['photo_price_cents' => 1490, 'video_price_cents' => 2490]);

        $photo = Media::factory()->photo()->for($event)->create(['price_cents' => 1490]);
        $video = Media::factory()->video()->for($event)->create(['price_cents' => 2490]);

        // Egy mar megvasarolt tetel — ennek az ara (az order_media snapshot) NEM valtozhat.
        $order = Order::factory()->create(['payment_status' => 'paid']);
        $order->media()->attach($photo->id, ['price_cents' => 1490]);

        $this->actingAs($admin)->put("/admin/events/{$event->id}", [
            'country_id' => $event->country_id,
            'name' => $event->name,
            'location' => $event->location,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'event_date' => $event->event_date->toDateString(),
            'starts_at' => $event->starts_at->format('Y-m-d H:i:s'),
            'status' => $event->status,
            'photo_price_cents' => 2000,
            'video_price_cents' => 2490,
        ])->assertRedirect();

        $this->assertSame(2000, $photo->fresh()->price_cents);
        $this->assertSame(2490, $video->fresh()->price_cents); // videó ára nem változott
        $this->assertSame(1490, (int) $order->media()->first()->pivot->price_cents); // megvásárolt ár rögzült
    }

    public function test_media_patch_no_longer_changes_price_only_visibility(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1490]);

        $this->actingAs($admin)->patch("/admin/media/{$media->id}", [
            'status' => Media::STATUS_HIDDEN,
            'price_cents' => 9999,
        ])->assertRedirect();

        $media->refresh();
        $this->assertSame(Media::STATUS_HIDDEN, $media->status);
        $this->assertSame(1490, $media->price_cents);
    }

    public function test_event_can_be_created_with_photo_and_video_prices(): void
    {
        $admin = User::factory()->admin()->create();
        $country = Country::factory()->create();

        $this->actingAs($admin)->post('/admin/events', [
            'country_id' => $country->id,
            'name' => 'Árazott Rally',
            'location' => 'Eger',
            'latitude' => 47.9,
            'longitude' => 20.37,
            'event_date' => '2026-10-01',
            'starts_at' => '2026-10-01T08:00',
            'status' => 'draft',
            'photo_price_cents' => 2200,
            'video_price_cents' => 4400,
        ])->assertRedirect();

        $event = Event::query()->where('name', 'Árazott Rally')->sole();
        $this->assertSame(2200, $event->photo_price_cents);
        $this->assertSame(4400, $event->video_price_cents);
        $this->assertSame(4400, $event->priceFor(Media::TYPE_VIDEO));
    }
}
