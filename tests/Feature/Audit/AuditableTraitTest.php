<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditableTraitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.audit.enabled', true);
        config()->set('boilerplate.audit.queue', false);
        config()->set('boilerplate.audit.events_allowlist', null);

        Schema::create('audit_widgets', function ($table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('secret')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_widgets');

        parent::tearDown();
    }

    public function test_created_event_is_recorded(): void
    {
        $widget = AuditWidget::create(['name' => 'foo']);

        $log = AuditLog::query()->where('event', 'audit_widget.created')->first();

        $this->assertNotNull($log);
        $this->assertSame($widget->id, $log->auditable_id);
        $this->assertSame('foo', $log->new_values['name']);
    }

    public function test_updated_event_records_diff(): void
    {
        $widget = AuditWidget::create(['name' => 'foo']);
        AuditLog::query()->delete();

        $widget->update(['name' => 'bar']);

        $log = AuditLog::query()->where('event', 'audit_widget.updated')->first();
        $this->assertNotNull($log);
        $this->assertSame(['name' => 'foo'], $log->old_values);
        $this->assertSame(['name' => 'bar'], $log->new_values);
    }

    public function test_updated_event_is_skipped_when_no_changes(): void
    {
        $widget = AuditWidget::create(['name' => 'foo']);
        AuditLog::query()->delete();

        $widget->save();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_deleted_event_is_recorded(): void
    {
        $widget = AuditWidget::create(['name' => 'foo']);
        AuditLog::query()->delete();

        $widget->delete();

        $log = AuditLog::query()->where('event', 'audit_widget.deleted')->first();
        $this->assertNotNull($log);
        $this->assertSame('foo', $log->old_values['name']);
    }

    public function test_excluded_attributes_are_dropped(): void
    {
        AuditWidget::create(['name' => 'foo', 'secret' => 'sssh']);

        $log = AuditLog::query()->where('event', 'audit_widget.created')->first();
        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('secret', $log->new_values);
    }
}

/**
 * @internal Test-only fixture exercising the Auditable trait.
 */
class AuditWidget extends Model
{
    use Auditable, HasUlids;

    protected $table = 'audit_widgets';

    protected $fillable = ['name', 'secret'];

    protected array $auditExclude = ['secret'];
}
