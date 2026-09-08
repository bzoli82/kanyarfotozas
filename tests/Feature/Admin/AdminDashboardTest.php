<?php

namespace Tests\Feature\Admin;

use App\Mail\OrderConfirmationMail;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_renders_kpis_and_charts_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertArrayHasKey('kpis', $props);
        $this->assertArrayHasKey('revenue_trend', $props['charts']);
        $this->assertArrayHasKey('security', $props);
    }

    public function test_photographer_cannot_view_admin_dashboard(): void
    {
        $photographer = User::factory()->photographer()->create();

        $this->actingAs($photographer)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_resend_email_button_resends_confirmation_for_paid_order(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create(['price_cents' => 1000]);
        $order = Order::factory()->paid()->create();
        $order->media()->attach($media->id, ['price_cents' => 1000]);

        $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/resend-email");

        $response->assertRedirect();
        Mail::assertQueued(OrderConfirmationMail::class, fn ($mail) => $mail->order->id === $order->id);
    }

    public function test_resend_email_rejects_unpaid_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create();

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/resend-email")->assertStatus(422);
    }
}
