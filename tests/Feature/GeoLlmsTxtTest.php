<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FaqItem;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\GeoSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoLlmsTxtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_llms_txt_lists_the_description_faq_and_live_events(): void
    {
        FaqItem::query()->create(['question_hu' => 'Kell regisztrálni?', 'answer_hu' => 'Nem kell.', 'category' => 'payment', 'active' => true, 'sort_order' => 1]);
        Event::factory()->create(['name' => 'Mátra Rally', 'location' => 'Mátraháza', 'status' => Event::STATUS_LIVE]);
        Event::factory()->draft()->create(['name' => 'Titkos Rally']);

        $body = $this->get('/llms.txt')->assertOk()->assertHeader('content-type', 'text/plain; charset=UTF-8')->getContent();

        $this->assertStringContainsString('motorsport', $body);
        $this->assertStringContainsString('Kell regisztrálni?', $body);
        $this->assertStringContainsString('Mátra Rally', $body);
        $this->assertStringNotContainsString('Titkos Rally', $body); // csak élő események
    }

    public function test_llms_txt_can_be_disabled(): void
    {
        SiteSetting::set('llms_txt_enabled', '0');

        $this->get('/llms.txt')->assertNotFound();
    }

    public function test_llms_txt_is_hidden_when_the_site_is_not_search_visible(): void
    {
        SiteSetting::set('seo_search_visible', '0');

        $this->get('/llms.txt')->assertNotFound();
    }

    public function test_superadmin_can_edit_the_geo_description(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->get('/admin/settings/geo')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Settings/Geo'));

        $this->actingAs($superadmin)->put('/admin/settings/geo', [
            'enabled' => true,
            'description' => 'Egyedi leírás az AI-keresőknek.',
        ])->assertRedirect();

        $this->assertSame('Egyedi leírás az AI-keresőknek.', app(GeoSettings::class)->description());
        $this->assertStringContainsString('Egyedi leírás az AI-keresőknek.', $this->get('/llms.txt')->getContent());
    }

    public function test_geo_settings_page_is_superadmin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/settings/geo')->assertForbidden();
    }
}
