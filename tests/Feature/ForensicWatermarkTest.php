<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ForensicWatermark;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ForensicWatermarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_embed_and_read_roundtrip_on_a_jpeg(): void
    {
        $service = app(ForensicWatermark::class);
        $jpeg = UploadedFile::fake()->image('a.jpg', 320, 240)->getContent();

        $stamped = $service->embed($jpeg, 4242, 77);

        $this->assertNotSame($jpeg, $stamped);
        $this->assertStringStartsWith("\xFF\xD8", $stamped); // még mindig érvényes JPEG kezdet

        $mark = $service->read($stamped);
        $this->assertSame(4242, $mark['order_id']);
        $this->assertSame(77, $mark['media_id']);
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $service = app(ForensicWatermark::class);
        $stamped = $service->embed('not-a-jpeg-body', 10, 20);

        // A záró markert kicsit elrontjuk.
        $broken = substr($stamped, 0, -3).'XYZ';

        $this->assertNull($service->read($broken));
    }

    public function test_unmarked_file_returns_null(): void
    {
        $this->assertNull(app(ForensicWatermark::class)->read(UploadedFile::fake()->image('clean.jpg')->getContent()));
    }

    public function test_download_serves_a_forensically_marked_photo(): void
    {
        Storage::fake('local');
        Storage::fake('nas');

        $media = Media::factory()->photo()->create([
            'status' => Media::STATUS_READY,
            'download_jpeg_s3_key' => 'downloads/99.jpg',
        ]);
        Storage::disk('local')->put('downloads/99.jpg', UploadedFile::fake()->image('orig.jpg', 300, 200)->getContent());

        $order = Order::factory()->paid()->create();
        $order->media()->attach($media->id, ['price_cents' => 1490]);
        $order->issueDownloadToken();

        $response = $this->get("/download/{$order->fresh()->download_token}/media/{$media->id}/jpeg");
        $response->assertOk();

        $mark = app(ForensicWatermark::class)->read($response->getContent());
        $this->assertNotNull($mark);
        $this->assertSame($order->id, $mark['order_id']);
        $this->assertSame($media->id, $mark['media_id']);

        $this->assertDatabaseHas('download_fingerprints', ['order_id' => $order->id, 'media_id' => $media->id, 'format' => 'jpeg']);
    }

    public function test_admin_can_identify_a_leaked_image(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $superadmin = User::factory()->superadmin()->create();
        $media = Media::factory()->photo()->create(['status' => Media::STATUS_READY]);
        $order = Order::factory()->paid()->create(['buyer_email' => 'leaker@example.com']);
        $order->media()->attach($media->id, ['price_cents' => 1490]);

        $leaked = app(ForensicWatermark::class)->embed(
            UploadedFile::fake()->image('shared.jpg', 400, 300)->getContent(),
            $order->id,
            $media->id,
        );
        $tmp = tempnam(sys_get_temp_dir(), 'leak').'.jpg';
        file_put_contents($tmp, $leaked);

        $this->actingAs($superadmin)
            ->post('/admin/forensics/identify', ['image' => new UploadedFile($tmp, 'shared.jpg', 'image/jpeg', null, true)])
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Forensics/Index')
                ->where('result.matched', true)
                ->where('result.buyer_email', 'leaker@example.com')
                ->where('result.order_id', $order->id));
    }

    public function test_forensic_watermark_can_be_toggled_off(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/forensics/toggle', ['enabled' => false])->assertRedirect();
        $this->assertFalse(ForensicWatermark::enabled());

        SiteSetting::set('forensic_watermark_enabled', '1');
        $this->assertTrue(ForensicWatermark::enabled());
    }
}
