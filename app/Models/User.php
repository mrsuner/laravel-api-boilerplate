<?php

namespace App\Models;

use App\Mail\PasswordResetLink;
use App\Mail\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'last_login_at',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the social accounts for the user.
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Send the password reset notification with a custom mail.
     */
    public function sendPasswordResetNotification($token): void
    {
        $frontendUrl = config('boilerplate.auth.frontend_url');
        $resetPath = config('boilerplate.auth.password_reset_url');
        $url = "{$frontendUrl}{$resetPath}?token={$token}&email=".urlencode($this->email);

        Mail::to($this->email)->send(new PasswordResetLink($url, $this->name));
    }

    /**
     * Override Laravel's default verification notification with our Mailable
     * and gate it on boilerplate.auth.email_verification.enabled. The
     * Registered event listener calls this after registration, so toggling
     * the config flag is enough to opt in or out of verification emails.
     */
    public function sendEmailVerificationNotification(): void
    {
        if (! config('boilerplate.auth.email_verification.enabled', true)) {
            return;
        }

        $expiry = (int) config('boilerplate.auth.email_verification.expire_minutes', 60);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes($expiry),
            [
                'id' => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ],
        );

        Mail::to($this->email)->send(new VerifyEmail($url, $this->name));
    }
}
