<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use App\Enums\PushProvider;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property DevicePlatform $platform
 * @property PushProvider $provider
 * @property string $push_token
 * @property string|null $device_id
 * @property string|null $device_name
 * @property string|null $app_version
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserDevice extends Model
{
    /** @use HasFactory<\Database\Factories\UserDeviceFactory> */
    use Auditable, HasFactory, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'platform',
        'provider',
        'push_token',
        'device_id',
        'device_name',
        'app_version',
        'last_used_at',
    ];

    /**
     * Raw push tokens are credentials — keep them out of audit payloads.
     *
     * @var list<string>
     */
    protected array $auditExclude = ['push_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'provider' => PushProvider::class,
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Register (or refresh) a device for a user, keyed by the globally unique
     * push token.
     *
     * Push tokens are recycled by the OS across reinstalls and account
     * switches, so the token — not the user — is the identity. Matching on
     * the token alone transfers an existing row to the new owner, guaranteeing
     * the previous user never receives notifications meant for whoever holds
     * the device now.
     *
     * @param  array{platform: DevicePlatform|string, provider: PushProvider|string, push_token: string, device_id?: string|null, device_name?: string|null, app_version?: string|null}  $attributes
     */
    public static function registerForUser(User $user, array $attributes): self
    {
        $device = static::query()->updateOrCreate(
            ['push_token' => $attributes['push_token']],
            [
                'user_id' => $user->getKey(),
                'platform' => $attributes['platform'],
                'provider' => $attributes['provider'],
                'device_id' => $attributes['device_id'] ?? null,
                'device_name' => $attributes['device_name'] ?? null,
                'app_version' => $attributes['app_version'] ?? null,
                'last_used_at' => Carbon::now(),
            ],
        );

        return $device;
    }

    /**
     * Scope a query to devices owned by the given user.
     *
     * @param  Builder<UserDevice>  $query
     * @return Builder<UserDevice>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    /**
     * Scope a query to devices on a given push provider.
     *
     * @param  Builder<UserDevice>  $query
     * @return Builder<UserDevice>
     */
    public function scopeForProvider(Builder $query, PushProvider $provider): Builder
    {
        return $query->where('provider', $provider->value);
    }
}
