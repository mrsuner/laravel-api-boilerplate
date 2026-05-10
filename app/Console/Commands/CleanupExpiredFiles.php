<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Removes uploaded files whose `expires_at` has elapsed and that have not been
 * claimed. Scheduled hourly in routes/console.php; can be invoked manually
 * with `--dry-run` to preview without deleting.
 */
class CleanupExpiredFiles extends Command
{
    protected $signature = 'files:cleanup {--dry-run : List files that would be removed without deleting them.}';

    protected $description = 'Delete expired, unclaimed file uploads from disk and the database.';

    public function handle(): int
    {
        if (! config('boilerplate.files.cleanup.enabled', true)) {
            $this->info('Files cleanup is disabled via config; skipping.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) config('boilerplate.files.cleanup.chunk_size', 100));
        $deleted = 0;

        File::query()
            ->withTrashed()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById($chunkSize, function ($files) use ($dryRun, &$deleted): void {
                foreach ($files as $file) {
                    if ($dryRun) {
                        $this->line("would delete {$file->id} ({$file->path} on {$file->disk})");
                        $deleted++;

                        continue;
                    }

                    Storage::disk($file->disk)->delete($file->path);
                    $file->forceDelete();
                    $deleted++;
                }
            });

        $verb = $dryRun ? 'would remove' : 'removed';
        $this->info("Files cleanup {$verb} {$deleted} expired file(s).");

        return self::SUCCESS;
    }
}
