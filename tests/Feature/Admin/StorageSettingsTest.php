<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class StorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_superadmin_can_view_storage_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $photographer = User::factory()->photographer()->create();
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get('/admin/settings/storage')->assertForbidden();
        $this->actingAs($photographer)->get('/admin/settings/storage')->assertForbidden();
        $this->actingAs($superadmin)->get('/admin/settings/storage')->assertOk();
    }

    public function test_superadmin_can_save_nas_connection_details(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $response = $this->actingAs($superadmin)->put('/admin/settings/storage', [
            'host' => 'nas.example.com',
            'port' => 22,
            'username' => 'photouser',
            'root' => '/media',
            'password' => 'sup3r-secret',
        ]);

        $response->assertRedirect();

        $this->assertSame('nas.example.com', SiteSetting::get('nas_host'));
        $this->assertSame('photouser', SiteSetting::get('nas_username'));

        $encrypted = SiteSetting::get('nas_password');
        $this->assertNotSame('sup3r-secret', $encrypted);
        $this->assertSame('sup3r-secret', Crypt::decryptString($encrypted));
    }

    public function test_blank_secret_fields_do_not_overwrite_the_stored_password(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/storage', [
            'host' => 'nas.example.com',
            'port' => 22,
            'username' => 'photouser',
            'root' => '/media',
            'password' => 'first-secret',
        ]);

        $this->actingAs($superadmin)->put('/admin/settings/storage', [
            'host' => 'nas.example.com',
            'port' => 22,
            'username' => 'photouser-renamed',
            'root' => '/media',
            'password' => '',
        ]);

        $this->assertSame('photouser-renamed', SiteSetting::get('nas_username'));
        $this->assertSame('first-secret', Crypt::decryptString(SiteSetting::get('nas_password')));
    }
}
