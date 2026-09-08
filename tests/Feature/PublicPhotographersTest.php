<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPhotographersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_photographers_page_shows_public_active_team_members(): void
    {
        $shown = User::factory()->photographer()->create(['name' => 'Nyilvános Fotós', 'is_active' => true, 'is_public' => true, 'bio' => 'Bemutatkozás.']);
        $adminShown = User::factory()->admin()->create(['name' => 'Nyilvános Admin', 'is_active' => true, 'is_public' => true]);
        User::factory()->photographer()->create(['name' => 'Privát Fotós', 'is_active' => true, 'is_public' => false]);
        User::factory()->photographer()->create(['name' => 'Inaktív Fotós', 'is_active' => false, 'is_public' => true]);

        $this->get('/photographers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Info/Photographers')
                ->where('photographers', fn ($list) => collect($list)->pluck('name')->all() === ['Nyilvános Fotós', 'Nyilvános Admin'])
                ->where('photographers.0.bio', 'Bemutatkozás.'));

        // csak név szerepeljen, e-mail soha
        $this->get('/photographers')->assertDontSee($shown->email)->assertDontSee($adminShown->email);
    }

    public function test_admin_can_upload_and_remove_a_photographer_avatar(): void
    {
        Storage::fake('public');

        $superadmin = User::factory()->superadmin()->create();
        $photographer = User::factory()->photographer()->create();

        $base = [
            'name' => $photographer->name,
            'email' => $photographer->email,
            'role' => 'photographer',
            'revenue_share_percent' => 70,
            'is_active' => true,
            'is_public' => true,
        ];

        $this->actingAs($superadmin)->put("/admin/photographers/{$photographer->id}", [
            ...$base,
            'avatar' => UploadedFile::fake()->image('me.jpg', 600, 800),
        ])->assertRedirect();

        $key = $photographer->fresh()->avatar_s3_key;
        $this->assertNotNull($key);
        $this->assertStringEndsWith('.webp', $key);
        Storage::disk('public')->assertExists($key);

        $this->actingAs($superadmin)->put("/admin/photographers/{$photographer->id}", [
            ...$base,
            'remove_avatar' => true,
        ])->assertRedirect();

        $this->assertNull($photographer->fresh()->avatar_s3_key);
        Storage::disk('public')->assertMissing($key);
    }
}
