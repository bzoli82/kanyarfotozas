<?php

namespace Tests\Feature\Admin;

use App\Models\HeroSlide;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeroSlideSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_only_superadmin_can_view_hero_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/settings/hero')->assertForbidden();
        $this->actingAs(User::factory()->photographer()->create())->get('/admin/settings/hero')->assertForbidden();
        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/settings/hero')->assertOk();
    }

    public function test_superadmin_can_upload_a_hero_image_and_it_is_converted_to_webp(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superadmin)->post('/admin/settings/hero', [
            'file' => UploadedFile::fake()->image('hero.jpg', 2560, 1440),
        ]);

        $response->assertRedirect();

        $slide = HeroSlide::query()->firstOrFail();
        $this->assertSame(HeroSlide::TYPE_IMAGE, $slide->type);
        $this->assertStringEndsWith('.webp', $slide->image_path);
        $this->assertSame('hero.jpg', $slide->original_filename);
        $this->assertTrue($slide->is_active);
        Storage::disk('public')->assertExists($slide->image_path);
    }

    public function test_superadmin_can_upload_a_hero_video_and_it_is_reencoded_with_a_poster(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $upload = new UploadedFile(base_path('tests/Fixtures/sample.mp4'), 'hero.mp4', 'video/mp4', null, true);

        $response = $this->actingAs($superadmin)->post('/admin/settings/hero', ['file' => $upload]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $slide = HeroSlide::query()->firstOrFail();
        $this->assertSame(HeroSlide::TYPE_VIDEO, $slide->type);
        $this->assertNull($slide->image_path);
        $this->assertStringEndsWith('.mp4', $slide->video_path);
        $this->assertStringEndsWith('.webp', $slide->poster_path);
        $this->assertGreaterThan(0, $slide->duration_seconds);
        Storage::disk('public')->assertExists($slide->video_path);
        Storage::disk('public')->assertExists($slide->poster_path);
    }

    public function test_upload_rejects_non_media_and_too_small_image(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->post('/admin/settings/hero', [
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($superadmin)->post('/admin/settings/hero', [
            'file' => UploadedFile::fake()->image('tiny.jpg', 400, 300),
        ])->assertSessionHasErrors('file');
    }

    public function test_toggle_active_and_delete_removes_all_files(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        Storage::disk('public')->put('hero/videos/x.mp4', 'binary');
        Storage::disk('public')->put('hero/x.webp', 'binary');
        $slide = HeroSlide::factory()->video()->create([
            'video_path' => 'hero/videos/x.mp4',
            'poster_path' => 'hero/x.webp',
        ]);

        $this->actingAs($superadmin)->put("/admin/settings/hero/{$slide->id}", ['is_active' => false])->assertRedirect();
        $this->assertFalse($slide->fresh()->is_active);

        $this->actingAs($superadmin)->delete("/admin/settings/hero/{$slide->id}")->assertRedirect();
        $this->assertModelMissing($slide);
        Storage::disk('public')->assertMissing('hero/videos/x.mp4');
        Storage::disk('public')->assertMissing('hero/x.webp');
    }

    public function test_reorder_updates_sort_order(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $a = HeroSlide::factory()->create(['sort_order' => 0]);
        $b = HeroSlide::factory()->create(['sort_order' => 1]);
        $c = HeroSlide::factory()->create(['sort_order' => 2]);

        $this->actingAs($superadmin)
            ->put('/admin/settings/hero/reorder', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect();

        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
    }

    public function test_homepage_exposes_active_hero_slides_in_order(): void
    {
        HeroSlide::factory()->create(['image_path' => 'hero/one.webp', 'sort_order' => 1]);
        HeroSlide::factory()->create(['image_path' => 'hero/two.webp', 'sort_order' => 0]);
        HeroSlide::factory()->inactive()->create(['image_path' => 'hero/hidden.webp', 'sort_order' => 2]);

        $slides = $this->get('/')->viewData('page')['props']['heroSlides'];

        $this->assertCount(2, $slides);
        $this->assertSame('image', $slides[0]['type']);
        $this->assertStringContainsString('hero/two.webp', $slides[0]['url']);
        $this->assertStringContainsString('hero/one.webp', $slides[1]['url']);
    }

    public function test_homepage_video_slide_carries_type_and_poster(): void
    {
        HeroSlide::factory()->video()->create([
            'video_path' => 'hero/videos/clip.mp4',
            'poster_path' => 'hero/clip.webp',
        ]);

        $slides = $this->get('/')->viewData('page')['props']['heroSlides'];

        $this->assertSame('video', $slides[0]['type']);
        $this->assertStringContainsString('hero/videos/clip.mp4', $slides[0]['url']);
        $this->assertStringContainsString('hero/clip.webp', $slides[0]['poster']);
    }
}
