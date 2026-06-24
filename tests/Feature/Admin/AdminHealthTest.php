<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PDOException;
use Tests\TestCase;

class AdminHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.admin.ip_whitelist.enabled', false);

        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->addRole('admin');

        Sanctum::actingAs($admin, ['admin']);
    }

    public function test_health_check_returns_ok_when_all_services_up(): void
    {
        $response = $this->getJson('/internal/admin/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.database', 'ok')
            ->assertJsonPath('data.checks.cache', 'ok')
            ->assertJsonPath('data.checks.queue', 'ok')
            ->assertJsonPath('data.checks.storage', 'ok');
    }

    public function test_health_check_returns_503_when_database_down(): void
    {
        // Capture the real database manager so it can be restored before the
        // RefreshDatabase teardown rolls back its transaction.
        $realManager = DB::getFacadeRoot();

        DB::shouldReceive('connection')
            ->andThrow(new PDOException('Database is down.'));

        $response = $this->getJson('/internal/admin/v1/health');

        DB::swap($realManager);

        $response->assertStatus(503)
            ->assertJsonPath('data.status', 'fail')
            ->assertJsonPath('data.checks.database', 'fail');
    }
}
