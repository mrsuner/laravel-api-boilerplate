<?php

namespace App\Services\Files;

use App\Models\File;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Coordinates file upload persistence, claim/release, and deletion. Acts as a
 * thin layer over the File model + filesystem so controllers stay focused on
 * HTTP concerns.
 */
class FileService
{
    /**
     * Persist an uploaded file to the configured disk and create the record.
     *
     * @param  array{
     *     visibility?: string|null,
     *     meta?: array<string, mixed>|null,
     *     uploader_type?: string|null,
     *     uploader_id?: string|null,
     * }  $attributes
     */
    public function store(UploadedFile $upload, array $attributes = []): File
    {
        $disk = (string) config('boilerplate.files.disk', 'local');
        $directory = $this->resolvePathTemplate(
            (string) config('boilerplate.files.path_template', 'uploads/{Y}/{m}'),
        );

        $extension = $upload->getClientOriginalExtension() ?: null;
        $filename = (string) \Illuminate\Support\Str::ulid().($extension ? '.'.$extension : '');
        $path = trim($directory, '/').'/'.$filename;

        Storage::disk($disk)->put($path, $upload->get(), [
            'visibility' => $this->visibilityFor($attributes['visibility'] ?? null),
        ]);

        $expiresMinutes = (int) config('boilerplate.files.default_expires_after_minutes', 1440);

        return File::query()->create([
            'disk' => $disk,
            'path' => $path,
            'client_name' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getMimeType() ?? 'application/octet-stream',
            'extension' => $extension,
            'size' => $upload->getSize() ?: 0,
            'checksum' => hash_file('sha256', $upload->getRealPath()) ?: null,
            'visibility' => $attributes['visibility'] ?? config('boilerplate.files.visibility', 'private'),
            'uploader_type' => $attributes['uploader_type'] ?? null,
            'uploader_id' => $attributes['uploader_id'] ?? null,
            'meta' => $attributes['meta'] ?? null,
            'expires_at' => $expiresMinutes > 0 ? Carbon::now()->addMinutes($expiresMinutes) : null,
        ]);
    }

    /**
     * Mark a file as persistent. Accepts a model or a ULID; throws if missing.
     */
    public function claim(File|string $file): File
    {
        return $this->resolve($file)->claim();
    }

    /**
     * Re-attach a TTL to a previously claimed file.
     */
    public function release(File|string $file, ?int $minutes = null): File
    {
        return $this->resolve($file)->release($minutes);
    }

    /**
     * Claim multiple files at once. Missing ids are silently skipped.
     *
     * @param  iterable<int, File|string>  $files
     * @return Collection<int, File>
     */
    public function claimMany(iterable $files): Collection
    {
        $ids = [];
        $models = [];

        foreach ($files as $file) {
            if ($file instanceof File) {
                $models[] = $file->claim();

                continue;
            }

            $ids[] = $file;
        }

        if ($ids !== []) {
            File::query()->whereIn('id', $ids)->whereNotNull('expires_at')
                ->update(['expires_at' => null]);

            $models = array_merge(
                $models,
                File::query()->whereIn('id', $ids)->get()->all(),
            );
        }

        return new Collection($models);
    }

    /**
     * Delete a file from the disk and remove the model row. Soft delete only —
     * the cleanup command performs the hard delete on expiry. Use forceDelete
     * via $file->forceDelete() if you need an immediate hard removal.
     */
    public function delete(File $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }

    private function resolve(File|string $file): File
    {
        if ($file instanceof File) {
            return $file;
        }

        return File::query()->findOrFail($file);
    }

    private function resolvePathTemplate(string $template): string
    {
        $now = Carbon::now();

        return strtr($template, [
            '{Y}' => $now->format('Y'),
            '{m}' => $now->format('m'),
            '{d}' => $now->format('d'),
        ]);
    }

    private function visibilityFor(?string $visibility): string
    {
        return $visibility === 'public' ? 'public' : 'private';
    }
}
