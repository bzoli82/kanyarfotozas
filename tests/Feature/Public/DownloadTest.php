<?php

namespace Tests\Feature\Public;

use App\Models\Media;
use App\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('nas');
    }

    private function paidOrderWithPhoto(): array
    {
        $media = Media::factory()->photo()->create([
            'status' => Media::STATUS_READY,
            'download_jpeg_s3_key' => 'downloads/photo.jpg',
            'download_webp_s3_key' => 'downloads/photo.webp',
        ]);
        Storage::disk('local')->put('downloads/photo.jpg', 'jpeg-bytes');
        Storage::disk('local')->put('downloads/photo.webp', 'webp-bytes');

        $order = Order::factory()->paid()->create();
        $order->media()->attach($media->id, ['price_cents' => $media->price_cents]);
        $order->issueDownloadToken();

        return [$order->fresh(), $media];
    }

    public function test_download_page_lists_purchased_items_and_decrements_uses(): void
    {
        [$order] = $this->paidOrderWithPhoto();

        $response = $this->get("/download/{$order->download_token}");

        $response->assertOk();
        $this->assertSame(4, $response->viewData('page')['props']['usesLeft']);
        $this->assertSame(1, $order->fresh()->download_token_uses);
    }

    public function test_unpaid_order_token_is_not_accessible(): void
    {
        $order = Order::factory()->create();
        $order->issueDownloadToken();

        $this->get("/download/{$order->download_token}")->assertNotFound();
    }

    public function test_expired_token_returns_gone(): void
    {
        [$order] = $this->paidOrderWithPhoto();
        $order->forceFill(['token_expires_at' => now()->subHour()])->save();

        $this->get("/download/{$order->download_token}")->assertStatus(410);
    }

    public function test_can_download_jpeg_and_webp_for_purchased_photo(): void
    {
        [$order, $media] = $this->paidOrderWithPhoto();

        $jpeg = $this->get("/download/{$order->download_token}/media/{$media->id}/jpeg");
        $webp = $this->get("/download/{$order->download_token}/media/{$media->id}/webp");

        $jpeg->assertOk();
        $webp->assertOk();
    }

    public function test_cannot_download_media_not_in_the_order(): void
    {
        [$order] = $this->paidOrderWithPhoto();
        $otherMedia = Media::factory()->photo()->create(['status' => Media::STATUS_READY]);

        $this->get("/download/{$order->download_token}/media/{$otherMedia->id}/jpeg")->assertForbidden();
    }

    public function test_video_download_serves_original_as_mp4(): void
    {
        $video = Media::factory()->video()->create([
            'status' => Media::STATUS_READY,
            'original_s3_key' => 'originals/clip.mp4',
        ]);
        Storage::disk('local')->put('originals/clip.mp4', 'mp4-bytes');

        $order = Order::factory()->paid()->create();
        $order->media()->attach($video->id, ['price_cents' => $video->price_cents]);
        $order->issueDownloadToken();

        $this->get("/download/{$order->download_token}/media/{$video->id}/mp4")->assertOk();
    }

    public function test_zip_endpoint_bundles_purchased_files(): void
    {
        [$order] = $this->paidOrderWithPhoto();

        $response = $this->get("/download/{$order->download_token}/zip");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
    }

    public function test_download_works_when_file_already_archived_to_nas(): void
    {
        $media = Media::factory()->photo()->create([
            'status' => Media::STATUS_READY,
            'download_jpeg_s3_key' => 'archive/photo.jpg',
            'download_webp_s3_key' => 'archive/photo.webp',
            'original_storage' => Media::STORAGE_NAS,
        ]);
        Storage::disk('nas')->put('archive/photo.jpg', 'jpeg-on-nas');

        $order = Order::factory()->paid()->create();
        $order->media()->attach($media->id, ['price_cents' => $media->price_cents]);
        $order->issueDownloadToken();

        $this->get("/download/{$order->download_token}/media/{$media->id}/jpeg")->assertOk();
    }
}
