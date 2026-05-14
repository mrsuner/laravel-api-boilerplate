<?php

namespace App\Jobs\Audit;

use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persists an audit log row asynchronously when the audit module is in
 * queued mode. The payload is the fully prepared attributes array produced
 * by AuditLogger::buildAttributes() — this job only writes, it does not
 * resolve user/context, so dispatcher-side data is preserved.
 */
class WriteAuditLog implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public function handle(): void
    {
        try {
            AuditLog::query()->create($this->attributes);
        } catch (Throwable $e) {
            Log::warning('Audit log queued write failed', [
                'event' => $this->attributes['event'] ?? null,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
