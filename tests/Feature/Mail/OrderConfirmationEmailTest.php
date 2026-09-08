<?php

namespace Tests\Feature\Mail;

use App\Mail\OrderConfirmationMail;
use App\Models\Event;
use App\Models\Media;
use App\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_confirmation_email_has_separate_jpeg_and_webp_download_links_for_photos(): void
    {
        $event = Event::factory()->create(['name' => 'Eger Kanyar']);
        $photo = Media::factory()->photo()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);

        $order = Order::factory()->paid()->create();
        $order->media()->attach($photo->id, ['price_cents' => 1490]);
        $order->issueDownloadToken();
        $token = $order->fresh()->download_token;

        $rendered = (new OrderConfirmationMail($order->fresh()))->render();

        $this->assertStringContainsString("/download/{$token}/media/{$photo->id}/jpeg", $rendered);
        $this->assertStringContainsString("/download/{$token}/media/{$photo->id}/webp", $rendered);
        $this->assertStringContainsString("/download/{$token}/zip", $rendered);
        $this->assertStringContainsString('Eger Kanyar', $rendered);
    }

    public function test_confirmation_email_uses_mp4_link_for_videos(): void
    {
        $video = Media::factory()->video()->create(['status' => Media::STATUS_READY]);
        $order = Order::factory()->paid()->create();
        $order->media()->attach($video->id, ['price_cents' => 1990]);
        $order->issueDownloadToken();
        $token = $order->fresh()->download_token;

        $rendered = (new OrderConfirmationMail($order->fresh()))->render();

        $this->assertStringContainsString("/download/{$token}/media/{$video->id}/mp4", $rendered);
        $this->assertStringNotContainsString("/media/{$video->id}/jpeg", $rendered);
    }
}
