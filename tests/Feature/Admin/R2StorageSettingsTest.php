<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\R2Storage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class R2StorageSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'endpoint' => 'https://acc123.r2.cloudflarestorage.com',
            'access_key_id' => 'AKIA-TEST',
            'secret_access_key' => 'super-secret-value',
            'public_bucket' => 'kf-public',
            'private_bucket' => 'kf-private',
            'import_bucket' => 'kf-import',
            'public_url' => 'https://media.kanyarfotozas.hu',
        ], $overrides);
    }

    public function test_only_superadmin_reaches_the_storage_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/settings/storage')->assertForbidden();
        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/settings/storage')->assertOk();
    }

    public function test_superadmin_saves_r2_keys_encrypted_and_applied_to_the_disks(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())
            ->put('/admin/settings/storage/r2', $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        // A secret titkosítva van tárolva.
        $stored = SiteSetting::get('r2_secret_access_key');
        $this->assertNotSame('super-secret-value', $stored);
        $this->assertSame('super-secret-value', Crypt::decryptString($stored));

        // Runtime configba töltve.
        app(R2Storage::class)->applyRuntimeConfig();
        $this->assertSame('kf-private', config('filesystems.disks.r2_private.bucket'));
        $this->assertSame('kf-import', config('filesystems.disks.r2_import.bucket'));
        $this->assertSame('https://media.kanyarfotozas.hu', config('filesystems.disks.r2_public.url'));
        $this->assertTrue(app(R2Storage::class)->isConfigured());
    }

    public function test_empty_secret_keeps_the_previous_one(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->put('/admin/settings/storage/r2', $this->payload())->assertRedirect();
        $this->actingAs($superadmin)->put('/admin/settings/storage/r2', $this->payload(['secret_access_key' => '', 'public_bucket' => 'kf-public-2']))->assertRedirect();

        $this->assertSame('super-secret-value', Crypt::decryptString(SiteSetting::get('r2_secret_access_key')));
        $this->assertSame('kf-public-2', SiteSetting::get('r2_public_bucket'));
    }

    public function test_endpoint_and_public_url_must_be_urls(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())
            ->put('/admin/settings/storage/r2', $this->payload(['endpoint' => 'not-a-url']))
            ->assertSessionHasErrors('endpoint');
    }

    public function test_form_prefill_flags_the_secret_without_exposing_it(): void
    {
        SiteSetting::set('r2_secret_access_key', Crypt::encryptString('x'));

        $form = app(R2Storage::class)->settingsForForm();

        $this->assertTrue($form['has_secret']);
        $this->assertArrayNotHasKey('secret_access_key', $form);
    }
}
