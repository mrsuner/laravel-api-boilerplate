<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Deletes audit_logs rows older than the configured retention window.
 * Invoked daily by routes/console.php when `audit.prune.enabled` is true;
 * can also be run manually with `--days=N` to override retention or
 * `--dry-run` to preview without deleting.
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune
        {--days= : Override the retention window from config.}
        {--dry-run : Count what would be removed without deleting.}';

    protected $description = 'Delete audit_logs rows older than the configured retention window.';

    public function handle(): int
    {
        if (! config('boilerplate.audit.prune.enabled', true)) {
            $this->info('Audit prune is disabled via config; skipping.');

            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?? config('boilerplate.audit.prune.days', 180));

        if ($days <= 0) {
            $this->warn('Retention days must be greater than zero; skipping.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) config('boilerplate.audit.prune.chunk_size', 1000));

        $query = AuditLog::query()->where('created_at', '<', $cutoff);

        if ($dryRun) {
            $count = (int) $query->count();
            $this->info("Audit prune would remove {$count} row(s) older than {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $batch = (int) $query->clone()->limit($chunkSize)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Audit prune removed {$deleted} row(s) older than {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
