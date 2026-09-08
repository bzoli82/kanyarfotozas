<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_it_only_lists_active_and_public_photographers(): void
    {
        $visible = User::factory()->photographer()->create([
            'name' => 'Kovács Péter',
            'is_active' => true,
            'is_public' => true,
        ]);

        User::factory()->photographer()->create(['is_active' => true, 'is_public' => false]);
        User::factory()->photographer()->create(['is_active' => false, 'is_public' => true]);
        User::factory()->admin()->create(['is_public' => true]);

        $response = $this->get('/api/photographers');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $visible->id);
        $response->assertJsonPath('data.0.name', 'Kovács Péter');
    }
}
