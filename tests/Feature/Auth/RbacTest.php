<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.rbac.enabled', true);
        config()->set('boilerplate.rbac.default_role', 'user');
    }

    private function registerExampleRoutes(): void
    {
        Route::middleware(['auth:sanctum', 'role:admin'])
            ->get('/test/admin-only', fn () => response()->json(['ok' => true]));

        Route::middleware(['auth:sanctum', 'permission:users.write'])
            ->get('/test/permission-gated', fn () => response()->json(['ok' => true]));
    }

    public function test_seeder_creates_configured_permissions_and_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['users.read', 'users.write', 'roles.read', 'roles.write'],
            Permission::query()->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(['admin', 'user'], Role::query()->pluck('name')->all());

        $admin = Role::query()->where('name', 'admin')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['users.read', 'users.write', 'roles.read', 'roles.write'],
            $admin->permissions->pluck('name')->all(),
        );

        $user = Role::query()->where('name', 'user')->firstOrFail();
        $this->assertCount(0, $user->permissions);
    }

    public function test_seeder_is_a_noop_when_disabled(): void
    {
        config()->set('boilerplate.rbac.enabled', false);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(0, Role::query()->count());
        $this->assertSame(0, Permission::query()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(2, Role::query()->count());
        $this->assertSame(4, Permission::query()->count());
    }

    public function test_registration_assigns_default_role(): void
    {
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $response = $this->postJson('/api/v1/auth/app/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200);

        $user = User::where('email', 'john@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_no_role_assigned_when_rbac_disabled(): void
    {
        Mail::fake();
        config()->set('boilerplate.rbac.enabled', false);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->postJson('/api/v1/auth/app/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(200);

        $user = User::where('email', 'john@example.com')->firstOrFail();
        $this->assertCount(0, $user->roles);
    }

    public function test_no_role_assigned_when_default_role_is_null(): void
    {
        Mail::fake();
        config()->set('boilerplate.rbac.default_role', null);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->postJson('/api/v1/auth/app/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(200);

        $user = User::where('email', 'john@example.com')->firstOrFail();
        $this->assertCount(0, $user->roles);
    }

    public function test_role_middleware_rejects_user_without_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->registerExampleRoutes();

        $user = User::factory()->create();
        $user->addRole('user');

        $response = $this->actingAs($user, 'sanctum')->getJson('/test/admin-only');

        $response->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_role_middleware_allows_user_with_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->registerExampleRoutes();

        $admin = User::factory()->create();
        $admin->addRole('admin');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/test/admin-only');

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_permission_middleware_rejects_user_without_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->registerExampleRoutes();

        $user = User::factory()->create();
        $user->addRole('user');

        $response = $this->actingAs($user, 'sanctum')->getJson('/test/permission-gated');

        $response->assertStatus(403);
    }

    public function test_permission_middleware_allows_user_with_inherited_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->registerExampleRoutes();

        $admin = User::factory()->create();
        $admin->addRole('admin');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/test/permission-gated');

        $response->assertOk();
    }

    public function test_user_is_able_to_check_works_programmatically(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->addRole('admin');

        $this->assertTrue($admin->isAbleTo('users.write'));
        $this->assertTrue($admin->hasRole('admin'));

        $user = User::factory()->create();
        $user->addRole('user');

        $this->assertFalse($user->isAbleTo('users.write'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_otp_first_time_signup_assigns_default_role(): void
    {
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->postJson('/api/v1/auth/app/otp', ['email' => 'newbie@example.com'])
            ->assertStatus(200);

        $token = \App\Models\Otp::where('identifier', 'newbie@example.com')->latest('id')->firstOrFail()->token;

        $this->postJson('/api/v1/auth/app/otp/verify', [
            'email' => 'newbie@example.com',
            'token' => $token,
        ])->assertStatus(200);

        $user = User::where('email', 'newbie@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
    }
}
