<?php

namespace Tests\Feature;

use App\Console\Commands\SchedulerHeartbeat;
use App\Models\SiteSetting;
use App\Services\SystemReadiness;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @return array<string, array<string, mixed>> csoportnév => {kulcs => item}
     */
    private function flatten(): array
    {
        $out = [];
        foreach (app(SystemReadiness::class)->groups() as $group) {
            foreach ($group['items'] as $item) {
                $out[$group['group']][$item['key']] = $item;
            }
        }

        return $out;
    }

    public function test_payments_group_is_critical_without_any_gateway(): void
    {
        config(['services.stripe.secret' => '', 'services.simplepay.merchant' => null, 'services.simplepay.secret_key' => null]);

        $items = $this->flatten()['Fizetés'];
        $this->assertSame('critical', $items['any']['status']);
    }

    public function test_payments_group_ok_when_a_gateway_is_configured(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);

        $items = $this->flatten()['Fizetés'];
        $this->assertSame('ok', $items['any']['status']);
        $this->assertSame('ok', $items['stripe']['status']);
    }

    public function test_scheduler_check_flags_a_stale_or_missing_heartbeat(): void
    {
        $items = $this->flatten()['Várólista & ütemezés'];
        $this->assertSame('warning', $items['scheduler']['status']); // soha nem futott

        SiteSetting::set(SchedulerHeartbeat::KEY, now()->subHour()->toIso8601String());
        $items = $this->flatten()['Várólista & ütemezés'];
        $this->assertSame('critical', $items['scheduler']['status']); // rég futott

        SiteSetting::set(SchedulerHeartbeat::KEY, now()->subMinutes(3)->toIso8601String());
        $items = $this->flatten()['Várólista & ütemezés'];
        $this->assertSame('ok', $items['scheduler']['status']);
    }

    public function test_base_price_and_app_key_checks(): void
    {
        $core = $this->flatten()['Alaprendszer'];
        $this->assertSame('ok', $core['app_key']['status']);
        $this->assertSame('warning', $core['base_price']['status']); // seeder nélkül nincs

        SiteSetting::set('base_price_huf', '1990');
        $core = $this->flatten()['Alaprendszer'];
        $this->assertSame('ok', $core['base_price']['status']);
    }
}
