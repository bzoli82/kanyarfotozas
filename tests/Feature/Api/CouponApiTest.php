<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_valid_coupon_returns_discount_preview(): void
    {
        Coupon::factory()->create(['code' => 'NYAR25', 'discount_percent' => 25]);

        $response = $this->postJson('/api/coupon/validate', ['code' => 'NYAR25', 'subtotal_cents' => 2000]);

        $response->assertOk()->assertJson(['valid' => true, 'discount_cents' => 500, 'discount_percent' => 25]);
    }

    public function test_unknown_coupon_returns_invalid(): void
    {
        $response = $this->postJson('/api/coupon/validate', ['code' => 'NEMLETEZIK', 'subtotal_cents' => 2000]);

        $response->assertStatus(422)->assertJson(['valid' => false]);
    }

    public function test_expired_coupon_returns_invalid(): void
    {
        Coupon::factory()->create(['code' => 'LEJART', 'discount_percent' => 10, 'expires_at' => now()->subDay()]);

        $response = $this->postJson('/api/coupon/validate', ['code' => 'LEJART', 'subtotal_cents' => 2000]);

        $response->assertStatus(422)->assertJson(['valid' => false]);
    }
}
