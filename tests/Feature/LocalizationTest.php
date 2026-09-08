<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Event;
use App\Models\FaqItem;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_locale_switch_stores_choice_in_session(): void
    {
        $this->put('/locale/en')->assertRedirect();
        $this->assertSame('en', session('app_locale'));

        $this->put('/locale/hu')->assertRedirect();
        $this->assertSame('hu', session('app_locale'));
    }

    public function test_unsupported_locale_is_ignored(): void
    {
        $this->put('/locale/de')->assertRedirect();
        $this->assertNull(session('app_locale'));
    }

    public function test_inertia_shares_locale_and_translations(): void
    {
        $response = $this->withSession(['app_locale' => 'en'])->get('/');

        $props = $response->viewData('page')['props'];
        $this->assertSame('en', $props['locale']);
        $this->assertSame('Home', $props['translations']['nav.home']);
    }

    public function test_translations_default_to_hungarian(): void
    {
        $props = $this->get('/')->viewData('page')['props'];

        $this->assertSame('hu', $props['locale']);
        $this->assertSame('Főoldal', $props['translations']['nav.home']);
    }

    public function test_faq_page_returns_english_content_when_locale_is_en(): void
    {
        FaqItem::query()->create([
            'question_hu' => 'Magyar kérdés',
            'answer_hu' => 'Magyar válasz',
            'question_en' => 'English question',
            'answer_en' => 'English answer',
            'category' => 'general',
            'active' => true,
            'sort_order' => 1,
        ]);

        $this->withSession(['app_locale' => 'en'])
            ->get('/faq')
            ->assertInertia(fn ($page) => $page
                ->where('categories.0.label', 'General')
                ->where('categories.0.items.0.question', 'English question')
                ->where('categories.0.items.0.answer', 'English answer')
            );
    }

    public function test_faq_falls_back_to_hungarian_when_english_missing(): void
    {
        FaqItem::query()->create([
            'question_hu' => 'Csak magyar',
            'answer_hu' => 'Csak magyar válasz',
            'question_en' => null,
            'answer_en' => null,
            'category' => 'payment',
            'active' => true,
            'sort_order' => 1,
        ]);

        $this->withSession(['app_locale' => 'en'])
            ->get('/faq')
            ->assertInertia(fn ($page) => $page
                ->where('categories.0.label', 'Payment')
                ->where('categories.0.items.0.question', 'Csak magyar')
            );
    }

    public function test_country_name_accessor_is_locale_aware(): void
    {
        $country = Country::query()->create([
            'code' => 'AT',
            'name_hu' => 'Ausztria',
            'name_en' => 'Austria',
            'flag_emoji' => '🇦🇹',
            'active' => true,
        ]);

        app()->setLocale('hu');
        $this->assertSame('Ausztria', $country->fresh()->name);

        app()->setLocale('en');
        $this->assertSame('Austria', $country->fresh()->name);
    }

    public function test_event_gallery_shows_english_country_name(): void
    {
        $country = Country::query()->create([
            'code' => 'HU',
            'name_hu' => 'Magyarország',
            'name_en' => 'Hungary',
            'flag_emoji' => '🇭🇺',
            'active' => true,
        ]);

        $event = Event::factory()->create([
            'name' => 'Teszt Kanyar',
            'location' => 'Teszthegy',
            'country_id' => $country->id,
            'status' => Event::STATUS_LIVE,
        ]);

        $this->withSession(['app_locale' => 'en'])
            ->get("/events/{$event->slug}")
            ->assertInertia(fn ($page) => $page->where('event.country.name', 'Hungary'));
    }
}
