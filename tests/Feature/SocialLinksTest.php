<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SocialLinks;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_superadmin_can_edit_social_links(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/settings/social')->assertForbidden();

        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin)->get('/admin/settings/social')->assertOk();
    }

    public function test_update_saves_and_normalizes_urls(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/social', [
            'facebook' => 'facebook.com/kanyarfotozas',
            'instagram' => 'https://instagram.com/kanyarfotozas',
            'youtube' => '',
            'tiktok' => '',
        ])->assertRedirect();

        $this->assertSame('https://facebook.com/kanyarfotozas', SiteSetting::get('social_facebook'));
        $this->assertSame('https://instagram.com/kanyarfotozas', SiteSetting::get('social_instagram'));
        $this->assertSame('', SiteSetting::get('social_youtube'));

        $links = app(SocialLinks::class)->all();
        $this->assertSame(['facebook', 'instagram'], collect($links)->pluck('platform')->all());
    }

    public function test_configured_links_are_shared_and_shown_on_the_contact_page(): void
    {
        SiteSetting::set('social_youtube', 'https://youtube.com/@kanyar');

        $this->get('/contact')->assertInertia(fn ($p) => $p
            ->component('Info/Contact')
            ->where('social', fn ($s) => count($s) === 1
                && $s[0]['url'] === 'https://youtube.com/@kanyar'
                && $s[0]['label'] === 'YouTube'
                && $s[0]['display'] === 'youtube.com/@kanyar'));
    }

    public function test_no_social_links_by_default(): void
    {
        $this->get('/contact')->assertInertia(fn ($p) => $p->where('social', []));
    }
}
