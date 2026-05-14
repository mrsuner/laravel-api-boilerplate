<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Opt-in trait that emits audit log entries for model lifecycle events.
 *
 * Generated event names follow `<model>.<verb>` using the snake_cased
 * model basename — e.g. App\Models\User emits `user.created`,
 * `user.updated`, `user.deleted`. Override `auditEventName(string $verb)`
 * on the model to use a different convention.
 *
 * Attributes listed in `$auditExclude` (or returned by `auditExcluded()`)
 * are dropped from the captured payload before redaction. Common
 * boilerplate (`created_at`, `updated_at`, `password`, `remember_token`)
 * is excluded by default — override `auditDefaultExcluded()` to change.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->recordAuditEvent('created', new: $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            $model->recordAuditEvent(
                'updated',
                old: $model->auditableAttributes($original),
                new: $model->auditableAttributes($changes),
            );
        });

        static::deleted(function (Model $model): void {
            $model->recordAuditEvent('deleted', old: $model->auditableAttributes($model->getOriginal()));
        });
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function recordAuditEvent(string $verb, array $old = [], array $new = []): void
    {
        app(AuditLogger::class)->logModelChange(
            $this,
            $this->auditEventName($verb),
            old: $old,
            new: $new,
        );
    }

    protected function auditEventName(string $verb): string
    {
        return Str::snake(class_basename($this)).'.'.$verb;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        $excluded = array_unique(array_merge(
            $this->auditDefaultExcluded(),
            $this->auditExcluded(),
        ));

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * @return list<string>
     */
    protected function auditDefaultExcluded(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'];
    }

    /**
     * @return list<string>
     */
    protected function auditExcluded(): array
    {
        /** @var list<string> $excluded */
        $excluded = property_exists($this, 'auditExclude') ? $this->auditExclude : [];

        return $excluded;
    }
}
