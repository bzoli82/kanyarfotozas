<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_photographer_can_open_their_own_profile_page(): void
    {
        $photographer = User::factory()->photographer()->create();

        $this->actingAs($photographer)->get('/profil')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Team/Profile')->where('profile.name', $photographer->name));
    }

    public function test_a_visitor_is_redirected_to_login(): void
    {
        $this->get('/profil')->assertRedirect('/login');
    }

    public function test_photographer_can_update_their_public_contacts_and_visibility(): void
    {
        $photographer = User::factory()->photographer()->create(['is_public' => false]);

        $this->actingAs($photographer)->post('/profil', [
            'bio' => 'Kanyarban élek.',
            'is_public' => true,
            'public_email' => 'en@pelda.hu',
            'website' => 'enfoto.hu',
            'social_facebook' => 'facebook.com/en',
            'social_instagram' => '',
            'social_youtube' => '',
            'social_tiktok' => 'tiktok.com/@en',
        ])->assertRedirect();

        $photographer->refresh();
        $this->assertTrue($photographer->is_public);
        $this->assertSame('Kanyarban élek.', $photographer->bio);
        $this->assertSame('en@pelda.hu', $photographer->public_email);
        $this->assertSame('enfoto.hu', $photographer->website);
        $this->assertSame('tiktok.com/@en', $photographer->social_tiktok);
    }

    public function test_photographer_cannot_change_role_or_commission_via_the_profile_page(): void
    {
        $photographer = User::factory()->photographer()->create(['revenue_share_percent' => 70]);

        $this->actingAs($photographer)->post('/profil', [
            'is_public' => true,
            'role' => 'superadmin',
            'revenue_share_percent' => 100,
            'email' => 'hijack@evil.test',
        ])->assertRedirect();

        $photographer->refresh();
        $this->assertSame('photographer', $photographer->role);
        $this->assertSame(70, $photographer->revenue_share_percent);
        $this->assertNotSame('hijack@evil.test', $photographer->email);
    }

    public function test_photographer_can_upload_and_remove_their_avatar(): void
    {
        Storage::fake('public');
        $photographer = User::factory()->photographer()->create();

        $this->actingAs($photographer)->post('/profil', [
            'is_public' => true,
            'avatar' => UploadedFile::fake()->image('me.jpg', 600, 600),
        ])->assertRedirect();

        $key = $photographer->fresh()->avatar_s3_key;
        $this->assertNotNull($key);
        Storage::disk('public')->assertExists($key);

        $this->actingAs($photographer)->post('/profil', [
            'is_public' => true,
            'remove_avatar' => true,
        ])->assertRedirect();

        $this->assertNull($photographer->fresh()->avatar_s3_key);
        Storage::disk('public')->assertMissing($key);
    }

    public function test_admin_can_also_edit_their_own_public_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/profil', ['is_public' => true, 'website' => 'admin.hu'])->assertRedirect();

        $this->assertSame('admin.hu', $admin->fresh()->website);
    }
}
