<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\DataSync;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DataSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    // ===================== FORRÁS OLDAL =====================

    public function test_manifest_endpoint_is_404_when_source_is_disabled(): void
    {
        $this->getJson('/api/sync/manifest', ['Authorization' => 'Bearer whatever'])->assertNotFound();
    }

    public function test_manifest_endpoint_is_404_with_a_wrong_token(): void
    {
        app(DataSync::class)->setSourceEnabled(true);

        $this->getJson('/api/sync/manifest', ['Authorization' => 'Bearer nope'])->assertNotFound();
    }

    public function test_manifest_endpoint_returns_counts_with_the_right_token(): void
    {
        $sync = app(DataSync::class);
        $sync->setSourceEnabled(true);
        Order::factory()->count(2)->create();

        $this->getJson('/api/sync/manifest', ['Authorization' => 'Bearer '.$sync->token()])
            ->assertOk()
            ->assertJsonPath('counts.orders', 2)
            ->assertJsonStructure(['generated_at', 'app_env', 'counts' => ['events', 'media', 'orders', 'messages', 'users']]);
    }

    public function test_superadmin_can_enable_the_source_and_gets_a_token(): void
    {
        $this->actingAs(User::factory()->superadmin()->create())
            ->put('/admin/settings/data-sync/source', ['enabled' => true])
            ->assertRedirect();

        $this->assertTrue(app(DataSync::class)->sourceEnabled());
        $this->assertNotEmpty(app(DataSync::class)->token());
    }

    public function test_regenerate_invalidates_the_old_token(): void
    {
        $sync = app(DataSync::class);
        $sync->setSourceEnabled(true);
        $old = $sync->token();

        $this->actingAs(User::factory()->superadmin()->create())
            ->put('/admin/settings/data-sync/source', ['enabled' => true, 'regenerate' => true])
            ->assertRedirect();

        $this->assertNotSame($old, app(DataSync::class)->token());
        $this->assertFalse(app(DataSync::class)->tokenMatches($old));
    }

    public function test_data_sync_page_is_superadmin_only(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/settings/data-sync')->assertForbidden();
        $this->actingAs(User::factory()->superadmin()->create())->get('/admin/settings/data-sync')->assertOk();
    }

    // ===================== CÉL OLDAL =====================

    public function test_save_remote_stores_the_connection_and_reports_the_manifest(): void
    {
        Http::fake([
            'https://eles.example/api/sync/manifest' => Http::response([
                'counts' => ['events' => 5, 'media' => 40, 'orders' => 3, 'messages' => 1, 'users' => 2],
            ]),
        ]);

        $this->actingAs(User::factory()->superadmin()->create())
            ->put('/admin/settings/data-sync/remote', ['url' => 'https://eles.example', 'token' => str_repeat('a', 48)])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, '5 esemény'));

        $this->assertTrue(app(DataSync::class)->hasRemote());
        $this->assertSame(['url' => 'https://eles.example', 'token' => str_repeat('a', 48)], app(DataSync::class)->remote());
    }

    public function test_pull_database_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(HttpException::class);

        app(DataSync::class)->pullDatabase(true);
    }

    public function test_restore_sql_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(HttpException::class);

        app(DataSync::class)->restoreSql('SELECT 1;');
    }

    public function test_pull_media_needs_r2_configured_locally(): void
    {
        config(['filesystems.disks.r2_public.key' => null]);

        $this->expectException(\RuntimeException::class);

        app(DataSync::class)->pullMedia(false);
    }

    public function test_scrub_deletes_secrets_and_anonymises_customer_data(): void
    {
        SiteSetting::set('stripe_secret', 'sk_live_supersecret');
        SiteSetting::set('theme_mode', 'dark'); // nem titok — marad

        $order = Order::factory()->create(['buyer_email' => 'valodi@ugyfel.hu', 'billing_name' => 'Valódi Név']);
        $user = User::factory()->superadmin()->create();
        $user->forceFill(['two_factor_secret' => 'ENCRYPTED', 'two_factor_confirmed_at' => now()])->save();

        $result = app(DataSync::class)->scrub();

        $this->assertSame(0, SiteSetting::query()->where('key', 'stripe_secret')->count());
        $this->assertSame('dark', SiteSetting::get('theme_mode'));

        $order->refresh();
        $this->assertStringContainsString('@pelda.helyi', $order->buyer_email);
        $this->assertNull($order->billing_name);

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        $this->assertGreaterThanOrEqual(1, $result['secrets_deleted']);
        $this->assertSame(1, $result['orders_anonymised']);
    }

    public function test_restore_sql_executes_statements_locally(): void
    {
        app(DataSync::class)->restoreSql('CREATE TABLE IF NOT EXISTS _datasync_probe (id int); DROP TABLE _datasync_probe;');

        // Ha idáig eljutottunk kivétel nélkül, a több-utasításos SQL lefutott.
        $this->assertTrue(true);
    }
}
