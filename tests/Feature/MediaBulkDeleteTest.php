<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaBulkDeleteTest extends TestCase
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

    /**
     * Egy médiát a tényleges fájljaival együtt hoz létre (a törlés ellenőrzéséhez).
     */
    private function mediaWithFiles(Event $event, array $overrides = []): Media
    {
        $media = Media::factory()->photo()->for($event)->create([
            'photographer_id' => $overrides['photographer_id'] ?? User::factory()->photographer(),
            'status' => Media::STATUS_READY,
            'thumbnail_s3_key' => 'thumbnails/'.fake()->uuid().'.webp',
            'watermarked_s3_key' => 'watermarked/'.fake()->uuid().'.webp',
            'original_s3_key' => 'originals/'.fake()->uuid().'.jpg',
            'original_storage' => Media::STORAGE_LOCAL,
            ...$overrides,
        ]);

        Storage::disk('public')->put($media->thumbnail_s3_key, 'x');
        Storage::disk('public')->put($media->watermarked_s3_key, 'x');
        Storage::disk('local')->put($media->original_s3_key, 'x');

        return $media;
    }

    public function test_admin_can_bulk_delete_media_and_all_derived_files(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $a = $this->mediaWithFiles($event);
        $b = $this->mediaWithFiles($event);
        $keep = $this->mediaWithFiles($event);

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/media/bulk-delete", ['ids' => [$a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('media', ['id' => $a->id]);
        $this->assertDatabaseMissing('media', ['id' => $b->id]);
        $this->assertDatabaseHas('media', ['id' => $keep->id]);

        Storage::disk('public')->assertMissing($a->thumbnail_s3_key);
        Storage::disk('public')->assertMissing($a->watermarked_s3_key);
        Storage::disk('local')->assertMissing($a->original_s3_key);
        Storage::disk('public')->assertExists($keep->thumbnail_s3_key);
    }

    public function test_bulk_delete_is_scoped_to_the_event(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();

        $mine = $this->mediaWithFiles($event);
        $foreign = $this->mediaWithFiles($otherEvent);

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/media/bulk-delete", ['ids' => [$mine->id, $foreign->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('media', ['id' => $mine->id]);
        $this->assertDatabaseHas('media', ['id' => $foreign->id]);
    }

    public function test_bulk_delete_can_also_remove_the_ftp_source_file_when_requested(): void
    {
        SiteSetting::set('nas_host', 'nas.example');
        SiteSetting::set('nas_username', 'importer');
        SiteSetting::set('nas_password', Crypt::encryptString('secret'));

        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        Storage::disk('nas')->put('incoming/rally/kanyar1.jpg', 'source-bytes');
        $media = $this->mediaWithFiles($event, ['import_source_path' => 'incoming/rally/kanyar1.jpg']);

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/media/bulk-delete", [
                'ids' => [$media->id],
                'delete_ftp_source' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('nas')->assertMissing('incoming/rally/kanyar1.jpg');
    }

    public function test_bulk_delete_keeps_the_ftp_source_file_by_default(): void
    {
        SiteSetting::set('nas_host', 'nas.example');
        SiteSetting::set('nas_username', 'importer');
        SiteSetting::set('nas_password', Crypt::encryptString('secret'));

        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        Storage::disk('nas')->put('incoming/rally/kanyar2.jpg', 'source-bytes');
        $media = $this->mediaWithFiles($event, ['import_source_path' => 'incoming/rally/kanyar2.jpg']);

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/media/bulk-delete", ['ids' => [$media->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('nas')->assertExists('incoming/rally/kanyar2.jpg');
    }

    public function test_photographer_bulk_delete_only_affects_their_own_media(): void
    {
        $mine = User::factory()->photographer()->create();
        $other = User::factory()->photographer()->create();
        $event = Event::factory()->create(['created_by' => $mine->id]);

        $ownMedia = $this->mediaWithFiles($event, ['photographer_id' => $mine->id]);
        $otherMedia = $this->mediaWithFiles($event, ['photographer_id' => $other->id]);

        $this->actingAs($mine)
            ->post("/admin/events/{$event->id}/media/bulk-delete", ['ids' => [$ownMedia->id, $otherMedia->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('media', ['id' => $ownMedia->id]);
        $this->assertDatabaseHas('media', ['id' => $otherMedia->id]);
    }

    public function test_bulk_delete_requires_at_least_one_id(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/media/bulk-delete", ['ids' => []])
            ->assertSessionHasErrors('ids');
    }
}
