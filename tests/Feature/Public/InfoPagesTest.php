<?php

namespace Tests\Feature\Public;

use App\Models\FaqItem;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfoPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_static_info_pages_render(): void
    {
        $this->get('/about')->assertOk();
        $this->get('/privacy')->assertOk();
        $this->get('/shop')->assertOk();
    }

    public function test_faq_page_groups_active_items_by_category_and_hides_inactive(): void
    {
        FaqItem::factory()->category('video')->create(['question_hu' => 'Van videó is?', 'active' => true]);
        FaqItem::factory()->category('payment')->create(['active' => true]);
        FaqItem::factory()->category('general')->inactive()->create();

        $response = $this->get('/faq');

        $response->assertOk();
        $categories = collect($response->viewData('page')['props']['categories']);
        $this->assertTrue($categories->contains('key', 'video'));
        $this->assertTrue($categories->contains('key', 'payment'));
        $this->assertFalse($categories->contains('key', 'general'));

        $videoCategory = $categories->firstWhere('key', 'video');
        $this->assertSame('Videó', $videoCategory['label']);
        $this->assertSame('Van videó is?', $videoCategory['items'][0]['question']);
    }
}
