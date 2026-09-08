<?php

namespace Tests\Feature\Public;

use App\Models\Media;
use App\Models\MediaShare;
use App\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function paidOrderWithMedia(): array
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY]);
        $order = Order::factory()->paid()->create();
        $order->media()->attach($media->id, ['price_cents' => 1490]);
        $order->issueDownloadToken();

        return [$order->fresh(), $media];
    }

    public function test_buyer_can_create_a_share_link_with_a_valid_download_token(): void
    {
        [$order, $media] = $this->paidOrderWithMedia();

        $response = $this->postJson("/media/{$media->id}/share", [
            'download_token' => $order->download_token,
            'platform' => 'link',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('/share/', $response->json('url'));
        $this->assertDatabaseHas('media_shares', ['media_id' => $media->id, 'order_id' => $order->id]);
    }

    public function test_share_creation_is_rejected_without_a_matching_purchase(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY]);

        $this->postJson("/media/{$media->id}/share", [
            'download_token' => (string) Str::uuid(),
        ])->assertStatus(403);
    }

    public function test_public_share_page_shows_the_watermarked_media_and_increments_views(): void
    {
        [$order, $media] = $this->paidOrderWithMedia();
        $media->forceFill(['watermarked_s3_key' => 'watermarked/x.webp'])->save();

        $share = MediaShare::query()->create(['media_id' => $media->id, 'order_id' => $order->id]);

        $props = $this->get("/share/{$share->share_token}")->viewData('page')['props'];
        $this->assertSame('watermarked/x.webp', $props['media']['watermarked_s3_key']);
        $this->assertArrayNotHasKey('download_jpeg_s3_key', $props['media']);

        $this->assertSame(1, $share->fresh()->view_count);
    }

    public function test_expired_share_is_404(): void
    {
        [$order, $media] = $this->paidOrderWithMedia();
        $share = MediaShare::query()->create([
            'media_id' => $media->id,
            'order_id' => $order->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->get("/share/{$share->share_token}")->assertNotFound();
    }
}
