<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_it_returns_only_ready_media_for_the_given_ids(): void
    {
        $ready = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1490]);
        $hidden = Media::factory()->create(['status' => Media::STATUS_HIDDEN]);

        $response = $this->getJson('/api/cart?'.http_build_query(['ids' => [$ready->id, $hidden->id, 999999]]));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertSame([$ready->id], $ids->all());
        $this->assertSame(1490, $response->json('data.0.price_cents'));
    }
}
