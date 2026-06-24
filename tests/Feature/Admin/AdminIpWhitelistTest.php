<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIpWhitelistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.admin.ip_whitelist.enabled', true);
        config()->set('boilerplate.admin.ip_whitelist.cidrs', ['100.64.0.0/10']);
    }

    public function test_request_is_blocked_when_ip_not_in_whitelist(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->postJson('/internal/admin/v1/auth/login', []);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden.']);
    }

    public function test_request_is_allowed_when_ip_matches_cidr(): void
    {
        // An IP inside the CIDR passes the whitelist and reaches validation,
        // which rejects the empty body with 422 — proving it was not blocked.
        $response = $this->withServerVariables(['REMOTE_ADDR' => '100.64.5.5'])
            ->postJson('/internal/admin/v1/auth/login', []);

        $response->assertStatus(422);
    }

    public function test_ip_whitelist_is_skipped_when_disabled_in_config(): void
    {
        config()->set('boilerplate.admin.ip_whitelist.enabled', false);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->postJson('/internal/admin/v1/auth/login', []);

        $response->assertStatus(422);
    }

    public function test_rejected_request_is_written_to_audit_log(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->postJson('/internal/admin/v1/auth/login', []);

        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.ip_rejected']);
    }
}
