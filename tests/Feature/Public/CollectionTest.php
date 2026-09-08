<?php

namespace Tests\Feature\Public;

use App\Models\CollectionShare;
use App\Models\Media;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_track_increments_wishlist_count(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'wishlist_count' => 0]);

        $this->postJson('/api/collection/track', ['media_id' => $media->id])->assertNoContent();

        $this->assertSame(1, $media->fresh()->wishlist_count);
    }

    public function test_share_creates_a_seven_day_token_and_resolves_to_a_page(): void
    {
        $media = Media::factory()->count(2)->create(['status' => Media::STATUS_READY]);

        $response = $this->postJson('/api/collection/share', ['media_ids' => $media->pluck('id')->all()]);

        $response->assertOk();
        $token = $response->json('token');
        $this->assertNotNull($token);

        $share = CollectionShare::query()->firstOrFail();
        $this->assertEqualsWithDelta(7, now()->diffInDays($share->expires_at), 1);

        $props = $this->get("/collection/share/{$token}")->viewData('page')['props'];
        $this->assertCount(2, $props['shared']['items']);
    }

    public function test_share_rejects_empty_or_invalid_media(): void
    {
        $this->postJson('/api/collection/share', ['media_ids' => [999999]])->assertStatus(422);
    }

    public function test_expired_collection_share_is_404(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY]);
        $share = CollectionShare::query()->create([
            'media_ids' => [$media->id],
            'expires_at' => now()->subDay(),
        ]);

        $this->get("/collection/share/{$share->share_token}")->assertNotFound();
    }

    public function test_collection_index_renders(): void
    {
        $this->get('/collection')->assertOk();
    }
}
