<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.admin.ip_whitelist.enabled', false);

        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
    }

    private function createAdmin(array $attributes = []): User
    {
        $admin = User::factory()->create(array_merge(['is_active' => true], $attributes));
        $admin->addRole('admin');

        return $admin;
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = $this->createAdmin(['password' => Hash::make('password123')]);

        $response = $this->postJson('/internal/admin/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'expires_at', 'user' => ['id', 'email', 'roles']]])
            ->assertJsonPath('data.user.roles', ['admin']);

        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.login']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $admin = $this->createAdmin(['password' => Hash::make('password123')]);

        $response = $this->postJson('/internal/admin/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_fails_for_non_admin_user(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/internal/admin/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $admin = $this->createAdmin([
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/internal/admin/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Account is inactive.']);
    }

    public function test_admin_can_logout(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('admin-session', ['admin'])->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/internal/admin/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out.']);

        $this->assertCount(0, $admin->fresh()->tokens);
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.logout']);
    }

    public function test_admin_can_get_own_profile(): void
    {
        $admin = $this->createAdmin();

        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/internal/admin/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.email', $admin->email)
            ->assertJsonPath('data.roles', ['admin']);
    }

    public function test_protected_route_requires_token(): void
    {
        $this->getJson('/internal/admin/v1/auth/me')->assertStatus(401);
    }

    public function test_token_without_admin_ability_is_forbidden(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api', ['some-other-ability'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/internal/admin/v1/auth/me')
            ->assertStatus(403);
    }
}
