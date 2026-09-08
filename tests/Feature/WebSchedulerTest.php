<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WebScheduler;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WebSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Bus::fake();
        // Olyan perc, amikor egyetlen ütemezett feladat sem esedékes → a schedule:run no-op.
        $this->travelTo(Carbon::parse('2026-06-15 10:03:00'));
    }

    private function superadmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
    }

    public function test_endpoint_is_404_when_disabled(): void
    {
        $token = app(WebScheduler::class)->token();

        $this->get("/api/ops/scheduler/{$token}")->assertNotFound();
    }

    public function test_endpoint_is_404_with_a_wrong_token(): void
    {
        app(WebScheduler::class)->setEnabled(true);

        $this->get('/api/ops/scheduler/nem-jo-token')->assertNotFound();
    }

    public function test_valid_hit_returns_plain_ok(): void
    {
        $scheduler = app(WebScheduler::class);
        $scheduler->setEnabled(true);

        $this->get('/api/ops/scheduler/'.$scheduler->token())
            ->assertOk()
            ->assertSee('OK', false);
    }

    public function test_hits_closer_than_the_minimum_interval_are_throttled(): void
    {
        $scheduler = app(WebScheduler::class);

        $this->assertSame('dispatched', $scheduler->tick());
        $this->assertSame('throttled', $scheduler->tick());

        // 60 mp múlva újra megy
        $this->travel(61)->seconds();
        $this->assertSame('dispatched', $scheduler->tick());
    }

    public function test_superadmin_enables_it_and_gets_a_url(): void
    {
        $props = $this->actingAs($this->superadmin())->get('/admin/settings/critical')->viewData('page')['props'];
        $this->assertFalse($props['webScheduler']['enabled']);
        $this->assertNull($props['webScheduler']['url']);

        $this->actingAs($this->superadmin())
            ->put('/admin/settings/critical/scheduler', ['enabled' => true])
            ->assertRedirect()->assertSessionHas('success');

        $props = $this->actingAs($this->superadmin())->get('/admin/settings/critical')->viewData('page')['props'];
        $this->assertTrue($props['webScheduler']['enabled']);
        $this->assertStringContainsString('/api/ops/scheduler/', $props['webScheduler']['url']);
    }

    public function test_regenerate_invalidates_the_old_token(): void
    {
        $scheduler = app(WebScheduler::class);
        $scheduler->setEnabled(true);
        $old = $scheduler->token();

        $this->actingAs($this->superadmin())
            ->put('/admin/settings/critical/scheduler', ['enabled' => true, 'regenerate' => true])
            ->assertRedirect();

        $new = app(WebScheduler::class)->token();

        $this->assertNotSame($old, $new);
        $this->get("/api/ops/scheduler/{$old}")->assertNotFound();
        $this->get("/api/ops/scheduler/{$new}")->assertOk();
    }

    public function test_scheduler_is_superadmin_only(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->put('/admin/settings/critical/scheduler', ['enabled' => true])->assertForbidden();
        $this->actingAs($admin)->post('/admin/settings/critical/scheduler/test')->assertForbidden();
    }

    public function test_test_button_runs_and_reports(): void
    {
        $this->actingAs($this->superadmin())
            ->post('/admin/settings/critical/scheduler/test')
            ->assertRedirect()
            ->assertSessionHas('success');
    }
}
