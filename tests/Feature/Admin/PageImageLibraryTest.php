<?php

namespace Tests\Feature\Admin;

use App\Models\HeroSlide;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageImageLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    private function putImage(string $key, int $w = 1200, int $h = 800): void
    {
        Storage::disk('public')->put($key, UploadedFile::fake()->image('x.jpg', $w, $h)->get());
    }

    public function test_only_superadmin_can_view_the_library(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/settings/images')->assertForbidden();
        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/settings/images')->assertOk();
    }

    public function test_index_lists_page_images_and_flags_orphans(): void
    {
        $this->putImage('hero/used.jpg');
        $this->putImage('seo/orphan.jpg');
        HeroSlide::factory()->create(['image_path' => 'hero/used.jpg']);

        $this->actingAs(User::factory()->superadmin()->create())
            ->get('/admin/settings/images')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/ImageLibrary')
                ->where('summary.total', 2)
                ->where('summary.orphans', 1)
            );
    }

    public function test_convert_rewrites_jpg_to_webp_and_updates_references(): void
    {
        $this->putImage('hero/slide.jpg');
        $slide = HeroSlide::factory()->create(['image_path' => 'hero/slide.jpg']);

        $this->actingAs(User::factory()->superadmin()->create())
            ->post('/admin/settings/images/convert', [
                'keys' => ['hero/slide.jpg'],
                'quality' => 80,
            ])
            ->assertRedirect();

        Storage::disk('public')->assertExists('hero/slide.webp');
        $this->assertSame('hero/slide.webp', $slide->fresh()->image_path);
    }

    public function test_delete_removes_orphans_but_blocks_used_files(): void
    {
        $this->putImage('seo/orphan.jpg');
        $this->putImage('hero/used.jpg');
        HeroSlide::factory()->create(['image_path' => 'hero/used.jpg']);

        $this->actingAs(User::factory()->superadmin()->create())
            ->post('/admin/settings/images/delete', [
                'keys' => ['seo/orphan.jpg', 'hero/used.jpg'],
            ])
            ->assertRedirect();

        Storage::disk('public')->assertMissing('seo/orphan.jpg');
        Storage::disk('public')->assertExists('hero/used.jpg');
    }
}
