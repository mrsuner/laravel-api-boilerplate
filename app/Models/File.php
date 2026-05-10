<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $disk
 * @property string $path
 * @property string $client_name
 * @property string $mime_type
 * @property string|null $extension
 * @property int $size
 * @property string|null $checksum
 * @property string $visibility
 * @property string|null $uploader_type
 * @property string|null $uploader_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $expires_at
 */
class File extends Model
{
    /** @use HasFactory<\Database\Factories\FileFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'disk',
        'path',
        'client_name',
        'mime_type',
        'extension',
        'size',
        'checksum',
        'visibility',
        'uploader_type',
        'uploader_id',
        'meta',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'meta' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function uploader(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Mark the file as persistent — clears the TTL so the cleanup command
     * will leave it alone. Idempotent.
     */
    public function claim(): static
    {
        if ($this->expires_at !== null) {
            $this->forceFill(['expires_at' => null])->save();
        }

        return $this;
    }

    /**
     * Re-attach a TTL to a previously claimed file. Useful when a parent
     * record drops the file reference but the file is no longer needed.
     */
    public function release(?int $minutes = null): static
    {
        $minutes ??= (int) config('boilerplate.files.default_expires_after_minutes', 1440);

        $this->forceFill(['expires_at' => Carbon::now()->addMinutes($minutes)])->save();

        return $this;
    }

    public function isClaimed(): bool
    {
        return $this->expires_at === null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
