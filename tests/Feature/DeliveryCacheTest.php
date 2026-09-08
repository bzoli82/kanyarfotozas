<?php

namespace Tests\Feature;

use App\Console\Commands\PurgeDeliveryCache;
use App\Exceptions\MediaHasSalesException;
use App\Jobs\PrepareOrderDownloads;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\MediaDeleter;
use App\Services\OrderFulfillment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliveryCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('nas');
    }

    private function paidOrder(): array
    {
        $media = Media::factory()->photo()->create([
            'status' => Media::STATUS_READY,
            'download_jpeg_s3_key' => 'downloads/p.jpg',
            'download_webp_s3_key' => 'downloads/p.webp',
        ]);
        Storage::disk('local')->put('downloads/p.jpg', 'jpeg-bytes');
        Storage::disk('local')->put('downloads/p.webp', 'webp-bytes');

        $order = Order::factory()->paid()->create();
        $order->media()->attach($media->id, ['price_cents' => $media->price_cents]);
        $order->issueDownloadToken();

        return [$order->fresh(), $media];
    }

    public function test_prepare_copies_purchased_files_into_the_delivery_disk(): void
    {
        [$order, $media] = $this->paidOrder();

        $ok = app(OrderFulfillment::class)->prepare($order);

        $this->assertTrue($ok);
        $this->assertSame('ready', $order->fresh()->fulfillment_status);
        Storage::disk('delivery')->assertExists("orders/{$order->id}/{$media->id}.jpg");
        Storage::disk('delivery')->assertExists("orders/{$order->id}/{$media->id}.webp");
    }

    public function test_download_is_served_from_cache_when_the_archive_is_unavailable(): void
    {
        [$order, $media] = $this->paidOrder();
        app(OrderFulfillment::class)->prepare($order);

        // Az archív "leáll" — a forrásfájlok eltűnnek.
        Storage::disk('local')->delete(['downloads/p.jpg', 'downloads/p.webp']);

        $this->get("/download/{$order->download_token}/media/{$media->id}/jpeg")->assertOk();
    }

    public function test_prepare_marks_failed_after_repeated_misses(): void
    {
        [$order, $media] = $this->paidOrder();
        Storage::disk('local')->delete(['downloads/p.jpg', 'downloads/p.webp']);

        $fulfillment = app(OrderFulfillment::class);
        for ($i = 0; $i < 8; $i++) {
            $fulfillment->prepare($order->fresh());
        }

        $this->assertSame('failed', $order->fresh()->fulfillment_status);
    }

    public function test_purge_command_clears_cache_for_dead_tokens(): void
    {
        [$order, $media] = $this->paidOrder();
        app(OrderFulfillment::class)->prepare($order);
        $order->forceFill(['token_expires_at' => now()->subHour()])->save();

        $this->artisan(PurgeDeliveryCache::class)->assertSuccessful();

        Storage::disk('delivery')->assertMissing("orders/{$order->id}/{$media->id}.jpg");
        $this->assertSame('pending', $order->fresh()->fulfillment_status);
    }

    public function test_markpaid_dispatches_the_prepare_job(): void
    {
        Bus::fake([PrepareOrderDownloads::class]);

        $media = Media::factory()->photo()->create(['status' => Media::STATUS_READY, 'price_cents' => 1490]);
        $order = Order::factory()->create(['payment_status' => Order::STATUS_PENDING]);
        $order->media()->attach($media->id, ['price_cents' => 1490]);

        app(CheckoutService::class)->markPaid($order);

        Bus::assertDispatched(PrepareOrderDownloads::class);
    }

    public function test_sold_media_cannot_be_hard_deleted(): void
    {
        [$order, $media] = $this->paidOrder();

        $this->expectException(MediaHasSalesException::class);
        app(MediaDeleter::class)->delete($media);
    }

    public function test_admin_delete_of_sold_media_shows_an_error(): void
    {
        [$order, $media] = $this->paidOrder();
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->delete("/admin/media/{$media->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }
}
