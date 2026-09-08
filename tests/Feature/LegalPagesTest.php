<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_public_pages_render_with_default_placeholder(): void
    {
        $this->get('/impresszum')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Info/Impressum')->where('bodyHtml', fn ($h) => str_contains($h, 'Impresszum')));

        $this->get('/aszf')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Info/Terms')->where('bodyHtml', fn ($h) => str_contains($h, 'Elállási jog')));
    }

    public function test_only_superadmin_can_edit_legal_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/settings/legal')->assertForbidden();

        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin)->get('/admin/settings/legal')->assertOk();
    }

    public function test_superadmin_can_save_markdown_and_it_renders_on_the_public_page(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/legal', [
            'impressum' => "## Impresszum\n\n**Cégnév:** Teszt Kft.",
            'terms' => "## ÁSZF\n\nEz a mi ÁSZF-ünk.",
        ])->assertRedirect();

        $this->assertSame("## ÁSZF\n\nEz a mi ÁSZF-ünk.", SiteSetting::get('legal_terms'));
        $this->assertNotNull(SiteSetting::get('legal_terms_updated_at'));

        $this->get('/impresszum')->assertInertia(fn ($p) => $p->where('bodyHtml', fn ($h) => str_contains($h, '<strong>Cégnév:</strong> Teszt Kft.')));
    }

    public function test_raw_html_in_markdown_is_stripped(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/legal', [
            'impressum' => 'Ártalmatlan <script>alert(1)</script> szöveg',
            'terms' => 'x',
        ])->assertRedirect();

        $this->get('/impresszum')->assertInertia(fn ($p) => $p->where('bodyHtml', fn ($h) => ! str_contains($h, '<script>')));
    }
}
