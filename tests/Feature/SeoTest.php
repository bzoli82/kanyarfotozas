<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SeoSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_robots_txt_disallows_private_areas_and_links_the_sitemap(): void
    {
        $res = $this->get('/robots.txt');

        $res->assertOk();
        $res->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $res->assertSee('Disallow: /admin', false);
        $res->assertSee('Disallow: /checkout', false);
        $res->assertSee('Sitemap: '.route('sitemap'), false);
    }

    public function test_sitemap_lists_static_pages_and_live_events_only(): void
    {
        $live = Event::factory()->create(['name' => 'Hungaroring Pályanap', 'slug' => 'hungaroring-palyanap', 'status' => Event::STATUS_LIVE]);
        $draft = Event::factory()->draft()->create(['slug' => 'titkos-esemeny']);

        $res = $this->get('/sitemap.xml');

        $res->assertOk();
        $res->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $res->assertSee(route('public.events.show', $live->slug), false);
        $res->assertSee(route('public.shop'), false);
        $res->assertDontSee($draft->slug, false);
    }

    public function test_home_page_has_canonical_and_website_json_ld(): void
    {
        $res = $this->get('/');

        $res->assertOk();
        $res->assertSee('<link rel="canonical"', false);
        $res->assertSee('property="og:site_name"', false);
        $res->assertSee('"@type":"WebSite"', false);
    }

    public function test_event_page_emits_event_specific_meta_and_json_ld(): void
    {
        $event = Event::factory()->create([
            'name' => 'Pannonia Ring Trackday',
            'slug' => 'pannonia-ring-trackday',
            'location' => 'Ostffyasszonyfa',
            'status' => Event::STATUS_LIVE,
        ]);

        $res = $this->get('/events/'.$event->slug.'?type=photo&page=2');

        $res->assertOk();
        $res->assertSee('property="og:title" content="Pannonia Ring Trackday', false);
        $res->assertSee('"@type":"Event"', false);
        // A canonical query string nélkül mutasson.
        $res->assertSee('<link rel="canonical" href="'.route('public.events.show', $event->slug).'"', false);
    }

    public function test_transactional_pages_are_noindex(): void
    {
        $this->get('/cart')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow"', false);
    }

    public function test_search_visible_off_makes_every_page_noindex_and_robots_disallow_all(): void
    {
        SiteSetting::set('seo_search_visible', '0');

        $this->get('/')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $res = $this->get('/robots.txt');
        $res->assertSee('Disallow: /', false);
        $res->assertDontSee('Sitemap:', false);
    }

    public function test_custom_description_and_title_suffix_reach_the_home_page(): void
    {
        SiteSetting::set('seo_description', 'Egyedi teszt leírás a főoldalhoz.');
        SiteSetting::set('seo_title_suffix', 'motorsport egyedi teszt');

        $this->get('/')
            ->assertSee('content="Egyedi teszt leírás a főoldalhoz."', false)
            ->assertSee('motorsport egyedi teszt', false);
    }

    public function test_google_verification_meta_is_rendered_when_set(): void
    {
        SiteSetting::set('seo_google_verification', 'abc123token');

        $this->get('/')->assertSee('<meta name="google-site-verification" content="abc123token">', false);
    }

    public function test_superadmin_can_update_seo_settings_and_upload_an_og_image(): void
    {
        Storage::fake('public');
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->get('/admin/settings/seo')->assertOk();

        $this->actingAs($superadmin)->post('/admin/settings/seo', [
            'description' => 'Frissített leírás.',
            'title_suffix' => 'motorsport fotók',
            'search_visible' => true,
            'google_verification' => '',
            'og_image' => UploadedFile::fake()->image('og.jpg', 1200, 630),
        ])->assertRedirect();

        $this->assertSame('Frissített leírás.', app(SeoSettings::class)->description());
        $this->assertNotNull(app(SeoSettings::class)->ogImagePath());
        Storage::disk('public')->assertExists(app(SeoSettings::class)->ogImagePath());
    }

    public function test_seo_settings_page_is_superadmin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get('/admin/settings/seo')
            ->assertForbidden();
    }
}
