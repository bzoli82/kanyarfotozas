<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        // A feltoltes utan azonnal dispatch-elt ProcessImageMedia/ArchiveMediaOriginalToNas
        // job sync queue-n fut a tesztekben — fake nelkul valos SFTP kapcsolatot probalna nyitni.
        Storage::fake('nas');
    }

    public function test_admin_can_batch_upload_photos_and_videos_for_a_chosen_photographer(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $response = $this->actingAs($admin)->post("/admin/events/{$event->id}/media", [
            'photographer_id' => $photographer->id,
            'files' => [
                UploadedFile::fake()->image('kanyar1.jpg'),
                new UploadedFile(base_path('tests/Fixtures/sample.mp4'), 'kanyar1.mp4', 'video/mp4', null, true),
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('media', 2);

        $photo = Media::query()->where('type', Media::TYPE_PHOTO)->sole();
        // A feltoltes utan a ProcessImageMedia job sync queue-n azonnal lefut a tesztekben,
        // tehat a kep mar feldolgozva 'ready' allapotban van.
        $this->assertSame(Media::STATUS_READY, $photo->status);
        $this->assertSame($photographer->id, $photo->photographer_id);
        Storage::disk('public')->assertExists($photo->thumbnail_s3_key);
        Storage::disk('public')->assertExists($photo->watermarked_s3_key);

        // Feldolgozas utan azonnal lefut a NAS-archivalo job is (sync queue a tesztekben),
        // ezert a fajl mar a NAS-on van, nem a lokalis diskon.
        $this->assertSame(Media::STORAGE_NAS, $photo->original_storage);
        Storage::disk('nas')->assertExists($photo->original_s3_key);

        $video = Media::query()->where('type', Media::TYPE_VIDEO)->sole();
        // A ProcessVideoMedia job is sync queue-n azonnal lefut (EPIC-05).
        $this->assertSame(Media::STATUS_READY, $video->status);
        Storage::disk('public')->assertExists($video->thumbnail_s3_key);
        Storage::disk('public')->assertExists($video->watermarked_s3_key);
    }

    public function test_photographer_uploads_are_attributed_to_themselves(): void
    {
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($photographer)->post("/admin/events/{$event->id}/media", [
            'files' => [UploadedFile::fake()->image('sajat.jpg')],
        ])->assertRedirect();

        $media = Media::sole();
        $this->assertSame($photographer->id, $media->photographer_id);
    }

    public function test_upload_rejects_disallowed_file_types(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $response = $this->actingAs($admin)->post("/admin/events/{$event->id}/media", [
            'photographer_id' => $photographer->id,
            'files' => [UploadedFile::fake()->create('malware.exe', 10)],
        ]);

        $response->assertSessionHasErrors('files.0');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_photographer_cannot_edit_another_photographers_media(): void
    {
        $owner = User::factory()->photographer()->create();
        $intruder = User::factory()->photographer()->create();
        $media = Media::factory()->create(['photographer_id' => $owner->id, 'price_cents' => 1000]);

        $response = $this->actingAs($intruder)->patch("/admin/media/{$media->id}", [
            'price_cents' => 5000,
        ]);

        $response->assertForbidden();
        $this->assertSame(1000, $media->fresh()->price_cents);
    }

    public function test_admin_can_hide_and_reveal_media(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create(['status' => Media::STATUS_READY]);

        $this->actingAs($admin)->patch("/admin/media/{$media->id}", ['status' => Media::STATUS_HIDDEN])
            ->assertRedirect();
        $this->assertSame(Media::STATUS_HIDDEN, $media->fresh()->status);
    }

    public function test_processing_media_status_cannot_be_manually_overridden(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->processing()->create();

        $this->actingAs($admin)->patch("/admin/media/{$media->id}", ['status' => Media::STATUS_READY])
            ->assertRedirect();

        $this->assertSame(Media::STATUS_PROCESSING, $media->fresh()->status);
    }

    public function test_admin_can_delete_media_and_its_stored_files(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create(['original_s3_key' => 'originals/1/test.jpg']);
        Storage::disk('local')->put($media->original_s3_key, 'fake-content');

        $this->actingAs($admin)->delete("/admin/media/{$media->id}")->assertRedirect();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('local')->assertMissing($media->original_s3_key);
    }
}
