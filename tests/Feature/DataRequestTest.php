<?php

namespace Tests\Feature;

use App\Mail\DataRequestVerifyMail;
use App\Models\DataRequest;
use App\Models\Media;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_public_can_submit_a_request_and_gets_a_verification_email(): void
    {
        Mail::fake();

        $this->post('/adatvedelem/kerelem', ['email' => 'Someone@Example.com', 'type' => 'delete'])
            ->assertRedirect();

        $req = DataRequest::query()->firstOrFail();
        $this->assertSame('someone@example.com', $req->email);
        $this->assertSame('pending', $req->status);
        Mail::assertQueued(DataRequestVerifyMail::class);
    }

    public function test_honeypot_silently_swallows_bots(): void
    {
        $this->post('/adatvedelem/kerelem', ['email' => 'a@b.hu', 'type' => 'export', 'website' => 'spam'])
            ->assertRedirect();

        $this->assertSame(0, DataRequest::query()->count());
    }

    public function test_verification_link_marks_request_verified(): void
    {
        $req = DataRequest::factory()->create(['status' => 'pending']);

        $this->get("/adatvedelem/kerelem/{$req->token}")->assertOk();

        $this->assertTrue($req->fresh()->isVerified());
    }

    public function test_admin_area_is_superadmin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get('/admin/data-requests')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPERADMIN]))
            ->get('/admin/data-requests')->assertOk();
    }

    public function test_export_download_returns_the_persons_data(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);
        $order = Order::factory()->paid()->create(['buyer_email' => 'buyer@example.com', 'total_cents' => 1000]);
        $order->media()->attach($media->id, ['price_cents' => 1000]);

        $req = DataRequest::factory()->verified()->create(['email' => 'buyer@example.com', 'type' => 'export']);

        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPERADMIN]))
            ->get("/admin/data-requests/{$req->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/json');

        $json = $response->json();
        $this->assertSame('buyer@example.com', $json['email']);
        $this->assertCount(1, $json['orders']);
    }

    public function test_delete_request_anonymises_orders_and_removes_aux_records(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $order = Order::factory()->paid()->create(['buyer_email' => 'gone@example.com']);
        $order->issueDownloadToken();
        DB::table('event_subscriptions')->insert(['email' => 'gone@example.com', 'location' => 'Eger', 'unsubscribe_token' => Str::uuid(), 'created_at' => now()]);

        $req = DataRequest::factory()->verified()->create(['email' => 'gone@example.com', 'type' => 'delete']);

        $this->actingAs($superadmin)->post("/admin/data-requests/{$req->id}/complete")->assertRedirect();

        $order->refresh();
        $this->assertStringNotContainsString('gone@example.com', $order->buyer_email);
        $this->assertNull($order->download_token);
        $this->assertSame(0, DB::table('event_subscriptions')->where('email', 'gone@example.com')->count());
        $this->assertSame('completed', $req->fresh()->status);
    }

    public function test_completing_a_non_verified_request_is_blocked(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $req = DataRequest::factory()->create(['status' => 'pending', 'type' => 'delete']);

        $this->actingAs($superadmin)->post("/admin/data-requests/{$req->id}/complete")->assertStatus(422);
    }
}
