<?php

namespace App\Services\Audit;

use App\Jobs\Audit\WriteAuditLog;
use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes append-only audit records. Resolves the acting user, request
 * context and event payload, redacts sensitive keys, then either inserts
 * synchronously or dispatches WriteAuditLog when queued mode is enabled.
 *
 * Audit failures are logged via Laravel's default channel but never
 * propagated — auditing must not break business logic.
 */
class AuditLogger
{
    /**
     * Log an arbitrary domain event.
     *
     * @param  array{
     *     user?: Authenticatable|string|null,
     *     old?: array<string, mixed>|null,
     *     new?: array<string, mixed>|null,
     *     metadata?: array<string, mixed>|null,
     * }  $payload
     */
    public function log(string $event, ?Model $auditable = null, array $payload = []): ?AuditLog
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if (! $this->eventAllowed($event)) {
            return null;
        }

        try {
            $attributes = $this->buildAttributes($event, $auditable, $payload);

            if ($this->shouldQueue()) {
                WriteAuditLog::dispatch($attributes)
                    ->onConnection(config('boilerplate.audit.queue_connection') ?: null)
                    ->onQueue((string) config('boilerplate.audit.queue_name', 'default'));

                return null;
            }

            return AuditLog::query()->create($attributes);
        } catch (Throwable $e) {
            Log::warning('Audit log write failed', [
                'event' => $event,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convenience wrapper for recording an eloquent model change.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $metadata
     */
    public function logModelChange(
        Model $model,
        string $event,
        array $old = [],
        array $new = [],
        array $metadata = [],
    ): ?AuditLog {
        return $this->log($event, $model, [
            'old' => $old,
            'new' => $new,
            'metadata' => $metadata,
        ]);
    }

    public function isEnabled(): bool
    {
        return (bool) config('boilerplate.audit.enabled', true);
    }

    /**
     * @param  array{
     *     user?: Authenticatable|string|null,
     *     old?: array<string, mixed>|null,
     *     new?: array<string, mixed>|null,
     *     metadata?: array<string, mixed>|null,
     * }  $payload
     * @return array<string, mixed>
     */
    protected function buildAttributes(string $event, ?Model $auditable, array $payload): array
    {
        $context = $this->resolveRequestContext();

        return [
            'id' => (string) Str::ulid(),
            'user_id' => $this->resolveUserId($payload['user'] ?? null),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey() ? (string) $auditable->getKey() : null,
            'old_values' => $this->prepareJson($payload['old'] ?? null),
            'new_values' => $this->prepareJson($payload['new'] ?? null),
            'metadata' => $this->prepareJson($payload['metadata'] ?? null),
            'ip_address' => $context['ip'],
            'user_agent' => $context['user_agent'],
            'request_id' => $context['request_id'],
            'created_at' => now(),
        ];
    }

    protected function resolveUserId(Authenticatable|string|null $user): ?string
    {
        if ($user instanceof Authenticatable) {
            return (string) $user->getAuthIdentifier();
        }

        if (is_string($user) && $user !== '') {
            return $user;
        }

        $current = Auth::user() ?? Auth::guard('sanctum')->user();

        return $current ? (string) $current->getAuthIdentifier() : null;
    }

    /**
     * @return array{ip: string|null, user_agent: string|null, request_id: string|null}
     */
    protected function resolveRequestContext(): array
    {
        if (! (bool) config('boilerplate.audit.capture_request_context', true)) {
            return ['ip' => null, 'user_agent' => null, 'request_id' => null];
        }

        if (! app()->bound('request')) {
            return ['ip' => null, 'user_agent' => null, 'request_id' => null];
        }

        /** @var Request $request */
        $request = app('request');

        $userAgent = $request->userAgent();
        $requestId = $request->headers->get('X-Request-Id');

        return [
            'ip' => $request->ip(),
            'user_agent' => $userAgent ? Str::limit($userAgent, 1000, '') : null,
            'request_id' => $requestId ? Str::limit($requestId, 64, '') : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function prepareJson(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return ['value' => $value];
        }

        if ($value === []) {
            return null;
        }

        return $this->redact($value);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    protected function redact(array $payload): array
    {
        $redactKeys = array_map(
            'strtolower',
            (array) config('boilerplate.audit.redact_keys', []),
        );

        if ($redactKeys === []) {
            return $payload;
        }

        $walk = function (array $items) use (&$walk, $redactKeys): array {
            $out = [];
            foreach ($items as $key => $value) {
                if (is_string($key) && in_array(strtolower($key), $redactKeys, true)) {
                    $out[$key] = '[REDACTED]';

                    continue;
                }

                $out[$key] = is_array($value) ? $walk($value) : $value;
            }

            return $out;
        };

        return $walk($payload);
    }

    protected function eventAllowed(string $event): bool
    {
        $allowlist = config('boilerplate.audit.events_allowlist');

        if ($allowlist === null) {
            return true;
        }

        return in_array($event, (array) $allowlist, true);
    }

    protected function shouldQueue(): bool
    {
        return (bool) config('boilerplate.audit.queue', false);
    }
}
