<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.admin.ip_whitelist.enabled', false);

        Role::query()->create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::query()->create(['name' => 'editor', 'display_name' => 'Editor']);
        Role::query()->create(['name' => 'viewer', 'display_name' => 'Viewer']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->addRole('admin');

        Sanctum::actingAs($this->admin, ['admin']);
    }

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/internal/admin/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links']);

        // 3 created + the acting admin.
        $this->assertSame(4, $response->json('meta.total'));
    }

    public function test_admin_can_filter_users_by_active_status(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);
        User::factory()->create(['is_active' => true]);

        $response = $this->getJson('/internal/admin/v1/users?is_active=false');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($inactive->id, $ids);
        $this->assertSame([$inactive->id], $ids);
    }

    public function test_admin_can_search_users_by_email(): void
    {
        $target = User::factory()->create(['email' => 'needle@example.com']);
        User::factory()->create(['email' => 'haystack@example.com']);

        $response = $this->getJson('/internal/admin/v1/users?search=needle');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $target->id);

        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_admin_can_view_user_detail(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/internal/admin/v1/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'recent_audit_logs']]);
    }

    public function test_admin_can_ban_regular_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/internal/admin/v1/users/{$user->id}/ban");

        $response->assertStatus(200)
            ->assertJson(['message' => 'User banned.']);

        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'admin.user.banned',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_admin_cannot_ban_another_admin(): void
    {
        $other = User::factory()->create();
        $other->addRole('admin');

        $response = $this->patchJson("/internal/admin/v1/users/{$other->id}/ban");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot ban another admin.']);

        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_ban_revokes_all_user_tokens(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->createToken('device-1');
        $user->createToken('device-2');

        $this->assertCount(2, $user->fresh()->tokens);

        $this->patchJson("/internal/admin/v1/users/{$user->id}/ban")
            ->assertStatus(200);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_admin_can_unban_user(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->patchJson("/internal/admin/v1/users/{$user->id}/unban");

        $response->assertStatus(200)
            ->assertJson(['message' => 'User unbanned.']);

        $this->assertTrue($user->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.user.unbanned']);
    }

    public function test_admin_can_sync_roles(): void
    {
        $user = User::factory()->create();
        $user->addRole('viewer');

        $response = $this->putJson("/internal/admin/v1/users/{$user->id}/roles", [
            'roles' => ['editor'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.roles', ['editor']);

        $user = $user->fresh();
        $this->assertTrue($user->hasRole('editor'));
        $this->assertFalse($user->hasRole('viewer'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.user.roles_synced']);
    }

    public function test_sync_roles_rejects_unknown_role(): void
    {
        $user = User::factory()->create();

        $response = $this->putJson("/internal/admin/v1/users/{$user->id}/roles", [
            'roles' => ['does-not-exist'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles.0']);
    }

    public function test_admin_can_assign_role(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/internal/admin/v1/users/{$user->id}/roles/editor");

        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->hasRole('editor'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.user.role_assigned']);
    }

    public function test_assign_unknown_role_returns_404(): void
    {
        $user = User::factory()->create();

        $this->postJson("/internal/admin/v1/users/{$user->id}/roles/ghost")
            ->assertStatus(404);
    }

    public function test_admin_can_revoke_role(): void
    {
        $user = User::factory()->create();
        $user->addRole('editor');

        $response = $this->deleteJson("/internal/admin/v1/users/{$user->id}/roles/editor");

        $response->assertStatus(200);
        $this->assertFalse($user->fresh()->hasRole('editor'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.user.role_revoked']);
    }

    public function test_admin_cannot_revoke_own_admin_role(): void
    {
        $response = $this->deleteJson("/internal/admin/v1/users/{$this->admin->id}/roles/admin");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot revoke your own admin role.']);

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    public function test_revoking_admin_role_revokes_admin_tokens(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $other->addRole('admin');
        $other->createToken('admin-session', ['admin']);
        $other->createToken('mobile-app');

        $response = $this->deleteJson("/internal/admin/v1/users/{$other->id}/roles/admin");

        $response->assertStatus(200);
        $this->assertFalse($other->fresh()->hasRole('admin'));
        $this->assertSame(1, $other->fresh()->tokens()->count());
        $this->assertSame('mobile-app', $other->fresh()->tokens()->first()->name);
    }
}
