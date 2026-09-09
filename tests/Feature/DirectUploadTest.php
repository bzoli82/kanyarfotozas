<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Media;
use App\Models\User;
use App\Services\FtpImport;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DirectUploadTest extends TestCase
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
        config(['media.import_disk' => 'r2_import']);
    }

    public function test_sign_reports_unsupported_when_the_disk_cannot_presign(): void
    {
        // A tesztben a faked (local) disk elvben tud presign URL-t; SFTP-nél nem —
        // ezt a FtpImport::providesDirectUpload() jelzi, itt lecseréljük.
        $this->partialMock(FtpImport::class, function ($mock) {
            $mock->shouldReceive('providesDirectUpload')->andReturnFalse();
        });

        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)
            ->postJson("/admin/events/{$event->id}/upload/sign", [
                'files' => [['path' => 'a.jpg', 'size' => 1000]],
            ])
            ->assertOk()
            ->assertJsonPath('supported', false);
    }

    public function test_admin_gets_presigned_urls_at_the_bucket_root(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $res = $this->actingAs($admin)->postJson("/admin/events/{$event->id}/upload/sign", [
            'files' => [
                ['path' => 'hungaroring/IMG_1.jpg', 'size' => 5000],
                ['path' => 'IMG_2.png', 'size' => 6000],
            ],
        ])->assertOk();

        $res->assertJsonPath('supported', true);
        $this->assertStringStartsWith("_upload/{$event->id}/", $res->json('import_path'));
        $this->assertCount(2, $res->json('files'));
        $this->assertStringContainsString("_upload/{$event->id}/", $res->json('files.0.url'));
        // Nem tartalmaz fotós-almappát az adminnál.
        $this->assertStringNotContainsString('fotosok/', $res->json('files.0.url'));
    }

    public function test_photographer_urls_are_confined_to_their_scope(): void
    {
        $photographer = User::factory()->photographer()->create();
        $photographer->refresh();
        $event = Event::factory()->create(['created_by' => $photographer->id]);

        $res = $this->actingAs($photographer)->postJson("/admin/events/{$event->id}/upload/sign", [
            'files' => [['path' => 'rally/a.jpg', 'size' => 5000]],
        ])->assertOk();

        $this->assertStringContainsString("fotosok/{$photographer->id}/_upload/{$event->id}/", $res->json('files.0.url'));
        $this->assertStringStartsWith("_upload/{$event->id}/", $res->json('import_path'));
    }

    public function test_traversal_in_the_path_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $this->actingAs($admin)->postJson("/admin/events/{$event->id}/upload/sign", [
            'files' => [['path' => '../../etc/passwd.jpg', 'size' => 10]],
        ])->assertStatus(422);
    }

    public function test_inactive_photographer_and_outsiders_are_forbidden(): void
    {
        $event = Event::factory()->create();
        $inactive = User::factory()->photographer()->create(['is_active' => false]);
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        $this->actingAs($inactive)->postJson("/admin/events/{$event->id}/upload/sign", ['files' => [['path' => 'a.jpg', 'size' => 1]]])->assertForbidden();
        $this->actingAs($organizer)->postJson("/admin/events/{$event->id}/upload/sign", ['files' => [['path' => 'a.jpg', 'size' => 1]]])->assertForbidden();
    }

    public function test_importing_an_upload_session_folder_purges_it_afterwards(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $event = Event::factory()->create();

        $session = "_upload/{$event->id}/20260908-120000-abcd1234";
        foreach (range(1, 3) as $i) {
            Storage::disk('r2_import')->put("{$session}/k{$i}.jpg", UploadedFile::fake()->image("k{$i}.jpg", 300 + $i * 40, 200)->getContent());
        }
        $this->assertTrue(Storage::disk('r2_import')->exists("{$session}/k1.jpg"));

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/import", [
                'photographer_id' => $photographer->id,
                'paths' => [$session],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(3, Media::query()->where('event_id', $event->id)->count());
        $this->assertFalse(Storage::disk('r2_import')->exists("{$session}/k1.jpg"), 'A feltöltő-mappa törlődött az import után.');
    }

    public function test_purge_command_removes_stale_upload_sessions(): void
    {

        Storage::disk('r2_import')->put('_upload/9/20200101-000000-old12345/a.jpg', 'x');
        $fresh = now()->format('Ymd-His').'-fresh1234';
        Storage::disk('r2_import')->put("_upload/9/{$fresh}/b.jpg", 'x');

        $this->artisan('roadsidephoto:purge-import-uploads')->assertSuccessful();

        $this->assertFalse(Storage::disk('r2_import')->exists('_upload/9/20200101-000000-old12345/a.jpg'));
        $this->assertTrue(Storage::disk('r2_import')->exists("_upload/9/{$fresh}/b.jpg"));
    }
}
