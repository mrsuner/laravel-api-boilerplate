<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.audit.enabled', true);
        config()->set('boilerplate.audit.prune.enabled', true);
        config()->set('boilerplate.audit.prune.days', 30);
        config()->set('boilerplate.audit.prune.chunk_size', 50);
    }

    public function test_prune_removes_rows_older_than_retention(): void
    {
        $old = AuditLog::factory()->create(['created_at' => now()->subDays(40)]);
        $fresh = AuditLog::factory()->create(['created_at' => now()->subDays(5)]);

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $fresh->id]);
    }

    public function test_dry_run_does_not_delete(): void
    {
        $old = AuditLog::factory()->create(['created_at' => now()->subDays(40)]);

        $this->artisan('audit:prune', ['--dry-run' => true])
            ->expectsOutputToContain('would remove')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $old->id]);
    }

    public function test_disabled_prune_is_a_noop(): void
    {
        config()->set('boilerplate.audit.prune.enabled', false);

        $old = AuditLog::factory()->create(['created_at' => now()->subDays(40)]);

        $this->artisan('audit:prune')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $old->id]);
    }

    public function test_days_option_overrides_config(): void
    {
        $row = AuditLog::factory()->create(['created_at' => now()->subDays(10)]);

        $this->artisan('audit:prune', ['--days' => 7])->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $row->id]);
    }
}
