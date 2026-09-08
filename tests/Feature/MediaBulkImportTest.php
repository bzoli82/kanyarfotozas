<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use App\Services\FtpImport;
use App\Services\MediaImportProgress;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaBulkImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('nas');
        Storage::fake('r2_import');

        // A tömeges import forrása a dedikált R2 „drop zone".
        config(['media.import_disk' => 'r2_import']);
    }

    private function putRemote(string $path): void
    {
        // Egyedi méret ⇒ egyedi tartalom (a SHA-256 duplikátum-szűrő miatt).
        $h = crc32($path);
        $w = 400 + ($h % 600);
        $ht = 300 + (intdiv($h, 600) % 600);
        Storage::disk('r2_import')->put($path, UploadedFile::fake()->image(basename($path), $w, $ht)->getContent());
    }

    public function test_disk_is_config_driven(): void
    {
        $this->assertSame('r2_import', FtpImport::disk());

        config(['media.import_disk' => 'nas']);
        $this->assertSame('nas', FtpImport::disk());
    }

    public function test_r2_import_is_available_when_the_faked_disk_exists(): void
    {
        $this->assertTrue(app(FtpImport::class)->isAvailable());
    }

    public function test_browse_lists_folders_from_r2(): void
    {
        $this->putRemote('rally-2026/a.jpg');
        $this->putRemote('rally-2026/b.jpg');
        $this->putRemote('teszt/c.jpg');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/admin/media-import/browse')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonCount(2, 'directories');

        $this->actingAs($admin)->getJson('/admin/media-import/browse?path=rally-2026')
            ->assertOk()
            ->assertJsonCount(2, 'files');
    }

    public function test_importing_a_whole_folder_expands_to_every_image(): void
    {
        foreach (range(1, 8) as $i) {
            $this->putRemote("rally/kanyar{$i}.jpg");
        }
        $this->putRemote('rally/almappa/extra.jpg'); // rekurzív is

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/import", [
                'photographer_id' => $photographer->id,
                'paths' => ['rally'], // egyetlen mappa
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // 9 kép <= inline_max (25) → azonnal létrejön
        $this->assertSame(9, Media::query()->where('event_id', $event->id)->count());
    }

    public function test_large_folder_runs_as_a_background_batch_with_progress(): void
    {
        config(['media.import_inline_max' => 3, 'media.import_chunk_size' => 5]);

        foreach (range(1, 12) as $i) {
            $this->putRemote("nagy/kep{$i}.jpg");
        }

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/import", [
                'photographer_id' => $photographer->id,
                'paths' => ['nagy'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Sync queue → a batch azonnal lefut.
        $this->assertSame(12, Media::query()->where('event_id', $event->id)->count());

        $status = $this->actingAs($admin)->getJson("/admin/events/{$event->id}/import/status")->assertOk();
        $status->assertJsonPath('finished', true)
            ->assertJsonPath('total', 12)
            ->assertJsonPath('imported', 12);
    }

    public function test_reimporting_the_same_folder_skips_everything(): void
    {
        foreach (range(1, 5) as $i) {
            $this->putRemote("rally/k{$i}.jpg");
        }

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $payload = ['photographer_id' => $photographer->id, 'paths' => ['rally']];

        $this->actingAs($admin)->post("/admin/events/{$event->id}/import", $payload)->assertSessionHas('success');
        $this->assertSame(5, Media::query()->where('event_id', $event->id)->count());

        $this->actingAs($admin)->post("/admin/events/{$event->id}/import", $payload);
        $this->assertSame(5, Media::query()->where('event_id', $event->id)->count(), 'Nem jött létre új média.');
    }

    public function test_preprocessed_folder_pairs_video_and_does_not_double_import_the_poster(): void
    {
        config(['media.video_mode' => 'preprocessed']);

        Storage::disk('r2_import')->put('rally/klip.mp4', file_get_contents(base_path('tests/Fixtures/sample.mp4')));
        Storage::disk('r2_import')->put('rally/klip_lores.mp4', 'lo-res-preview');
        Storage::disk('r2_import')->put('rally/klip.jpg', UploadedFile::fake()->image('klip.jpg', 320, 180)->getContent());
        $this->putRemote('rally/sima-foto.jpg');

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/import", [
                'photographer_id' => $photographer->id,
                'paths' => ['rally'],
            ])
            ->assertSessionHas('success');

        // 1 videó (a klip.jpg a poszter, NEM külön fotó) + 1 sima fotó = 2
        $this->assertSame(2, Media::query()->where('event_id', $event->id)->count());
        $this->assertSame(1, Media::query()->where('event_id', $event->id)->where('type', Media::TYPE_VIDEO)->count());
        $this->assertSame(1, Media::query()->where('event_id', $event->id)->where('type', Media::TYPE_PHOTO)->count());
    }

    public function test_photographers_cannot_use_bulk_import(): void
    {
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($photographer)->getJson('/admin/media-import/browse')->assertForbidden();
        $this->actingAs($photographer)
            ->post("/admin/events/{$event->id}/import", ['photographer_id' => $photographer->id, 'paths' => ['x']])
            ->assertForbidden();
    }

    public function test_empty_selection_reports_nothing_to_import(): void
    {
        Storage::disk('r2_import')->put('rally/jegyzet.txt', 'not an image');

        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/import", [
                'photographer_id' => $photographer->id,
                'paths' => ['rally'],
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Media::query()->count());
    }

    public function test_progress_helper_returns_null_without_a_run(): void
    {
        $event = Event::factory()->create();
        $this->assertNull(app(MediaImportProgress::class)->forEvent($event->id));
    }
}
