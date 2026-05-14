<?php

use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('audit_log')) {
    /**
     * Append an entry to the audit trail.
     *
     * Silently returns null when the audit module is disabled or when the
     * write fails — auditing must never break business logic.
     *
     * @param  array{
     *     user?: \Illuminate\Contracts\Auth\Authenticatable|string|null,
     *     old?: array<string, mixed>|null,
     *     new?: array<string, mixed>|null,
     *     metadata?: array<string, mixed>|null,
     * }  $payload
     */
    function audit_log(string $event, ?Model $auditable = null, array $payload = []): ?AuditLog
    {
        return app(AuditLogger::class)->log($event, $auditable, $payload);
    }
}
