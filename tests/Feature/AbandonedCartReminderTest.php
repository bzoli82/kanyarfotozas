<?php

namespace Tests\Feature;

use App\Mail\AbandonedCartMail;
use App\Models\Event;
use App\Models\Media;
use App\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AbandonedCartReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
    }

    private function pendingOrder(array $overrides = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'buyer_email' => 'vevo@example.com',
            'payment_status' => Order::STATUS_PENDING,
            'created_at' => now()->subHours(30),
        ], $overrides));

        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);
        $order->media()->attach($media->id, ['price_cents' => 1000]);

        return $order;
    }

    public function test_sends_one_reminder_for_an_abandoned_order(): void
    {
        $order = $this->pendingOrder();

        $this->artisan('roadsidephoto:send-abandoned-cart-reminders')->assertSuccessful();

        Mail::assertQueued(AbandonedCartMail::class, fn ($mail) => $mail->order->id === $order->id);
        $this->assertNotNull($order->fresh()->abandoned_reminder_sent_at);
    }

    public function test_does_not_send_twice(): void
    {
        $this->pendingOrder(['abandoned_reminder_sent_at' => now()->subHour()]);

        $this->artisan('roadsidephoto:send-abandoned-cart-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_skips_orders_that_are_too_fresh_or_too_old(): void
    {
        $this->pendingOrder(['created_at' => now()->subHours(5)]);
        $this->pendingOrder(['created_at' => now()->subHours(100)]);

        $this->artisan('roadsidephoto:send-abandoned-cart-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_skips_but_marks_when_the_buyer_paid_since(): void
    {
        $order = $this->pendingOrder();
        Order::factory()->create([
            'buyer_email' => 'vevo@example.com',
            'payment_status' => Order::STATUS_PAID,
            'created_at' => now()->subHours(10),
        ]);

        $this->artisan('roadsidephoto:send-abandoned-cart-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertNotNull($order->fresh()->abandoned_reminder_sent_at);
    }

    public function test_resume_link_repopulates_the_cart(): void
    {
        $event = Event::factory()->create();
        $order = Order::factory()->create(['payment_status' => Order::STATUS_PENDING]);
        $media = Media::factory()->create(['event_id' => $event->id, 'status' => Media::STATUS_READY]);
        $order->media()->attach($media->id, ['price_cents' => 1000]);

        $url = URL::temporarySignedRoute('public.cart', now()->addDays(7), ['order' => $order->id]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cart/Index')
                ->has('resumeItems', 1)
                ->where('resumeItems.0.id', $media->id));
    }

    public function test_invalid_signature_yields_no_resume_items(): void
    {
        $order = Order::factory()->create(['payment_status' => Order::STATUS_PENDING]);

        $this->get('/cart?order='.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('resumeItems', []));
    }
}
