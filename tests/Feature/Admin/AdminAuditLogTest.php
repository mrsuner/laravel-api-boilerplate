<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
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

    public function test_admin_can_list_audit_logs(): void
    {
        AuditLog::factory()->count(3)->create();

        $response = $this->getJson('/internal/admin/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links']);

        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_admin_can_filter_audit_logs_by_user(): void
    {
        $user = User::factory()->create();
        AuditLog::factory()->forUser($user)->create();
        AuditLog::factory()->count(2)->create();

        $response = $this->getJson("/internal/admin/v1/audit-logs?user_id={$user->id}");

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($user->id, $response->json('data.0.user_id'));
    }

    public function test_admin_can_filter_audit_logs_by_event(): void
    {
        AuditLog::factory()->event('admin.login')->create();
        AuditLog::factory()->event('admin.logout')->create();

        $response = $this->getJson('/internal/admin/v1/audit-logs?event=admin.login');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('admin.login', $response->json('data.0.event'));
    }

    public function test_admin_can_filter_audit_logs_by_date_range(): void
    {
        $recent = AuditLog::factory()->create();

        $old = AuditLog::factory()->create();
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $from = now()->subDays(2)->toDateString();
        $to = now()->toDateString();

        $response = $this->getJson("/internal/admin/v1/audit-logs?from={$from}&to={$to}");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_admin_can_view_single_audit_log(): void
    {
        $log = AuditLog::factory()->create();

        $this->getJson("/internal/admin/v1/audit-logs/{$log->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $log->id);
    }

    public function test_admin_can_view_user_audit_logs(): void
    {
        $user = User::factory()->create();
        AuditLog::factory()->forUser($user)->count(2)->create();
        AuditLog::factory()->create();

        $response = $this->getJson("/internal/admin/v1/users/{$user->id}/audit-logs");

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('meta.total'));
    }
}
