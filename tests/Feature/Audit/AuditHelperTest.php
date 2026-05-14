<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditHelperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.audit.enabled', true);
        config()->set('boilerplate.audit.queue', false);
    }

    public function test_helper_writes_an_entry(): void
    {
        $user = User::factory()->create();

        $log = audit_log('users.invited', $user, [
            'metadata' => ['invited_by' => 'admin'],
        ]);

        $this->assertNotNull($log);
        $this->assertSame('users.invited', $log->event);
        $this->assertSame($user->id, $log->auditable_id);
        $this->assertSame(['invited_by' => 'admin'], $log->metadata);
    }

    public function test_helper_returns_null_when_disabled(): void
    {
        config()->set('boilerplate.audit.enabled', false);

        $this->assertNull(audit_log('users.invited'));
    }
}
