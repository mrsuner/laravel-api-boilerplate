<?php

namespace Tests\Feature\Audit;

use App\Jobs\Audit\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.audit.enabled', true);
        config()->set('boilerplate.audit.queue', false);
        config()->set('boilerplate.audit.events_allowlist', null);
        config()->set('boilerplate.audit.capture_request_context', true);
    }

    public function test_log_persists_an_entry(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        app(AuditLogger::class)->log('users.viewed', $user, [
            'metadata' => ['source' => 'profile'],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'users.viewed',
            'user_id' => $user->id,
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
        ]);
    }

    public function test_log_is_a_noop_when_disabled(): void
    {
        config()->set('boilerplate.audit.enabled', false);

        $result = app(AuditLogger::class)->log('auth.login');

        $this->assertNull($result);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_log_redacts_sensitive_keys(): void
    {
        $log = app(AuditLogger::class)->log('users.password_reset', null, [
            'new' => [
                'password' => 'super-secret',
                'email' => 'a@b.test',
                'nested' => ['token' => 'abc', 'safe' => 1],
            ],
        ]);

        $this->assertNotNull($log);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertSame('a@b.test', $log->new_values['email']);
        $this->assertSame('[REDACTED]', $log->new_values['nested']['token']);
        $this->assertSame(1, $log->new_values['nested']['safe']);
    }

    public function test_events_allowlist_filters_writes(): void
    {
        config()->set('boilerplate.audit.events_allowlist', ['auth.login']);

        app(AuditLogger::class)->log('auth.login');
        app(AuditLogger::class)->log('users.deleted');

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login']);
    }

    public function test_queue_mode_dispatches_job_instead_of_writing(): void
    {
        Queue::fake();
        config()->set('boilerplate.audit.queue', true);

        app(AuditLogger::class)->log('auth.login');

        Queue::assertPushed(WriteAuditLog::class);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_log_swallows_exceptions(): void
    {
        config()->set('boilerplate.audit.table', 'audit_logs_does_not_exist');

        $result = app(AuditLogger::class)->log('auth.login');

        $this->assertNull($result);
    }

    public function test_captures_request_ip_and_user_agent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withServerVariables([
                'HTTP_USER_AGENT' => 'phpunit-agent',
                'HTTP_X_REQUEST_ID' => 'req-123',
                'REMOTE_ADDR' => '198.51.100.7',
            ])
            ->get('/'); // boot a request to populate the container

        app(AuditLogger::class)->log('auth.login', $user);

        $log = AuditLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame('phpunit-agent', $log->user_agent);
        $this->assertSame('req-123', $log->request_id);
    }
}
