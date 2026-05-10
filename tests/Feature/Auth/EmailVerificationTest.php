<?php

namespace Tests\Feature\Auth;

use App\Mail\VerifyEmail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.auth.email_verification.enabled', true);
    }

    private function signedVerificationUrl(User $user, ?int $expireMinutes = null): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes($expireMinutes ?? (int) config('boilerplate.auth.email_verification.expire_minutes', 60)),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        );
    }

    public function test_registration_sends_verification_email_when_enabled(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/app/register', [
            'name' => 'Verify Me',
            'email' => 'verify-me@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(200);

        Mail::assertSent(VerifyEmail::class, function ($mail) {
            return $mail->hasTo('verify-me@example.com');
        });
    }

    public function test_registration_skips_email_when_verification_disabled(): void
    {
        config()->set('boilerplate.auth.email_verification.enabled', false);
        Mail::fake();

        $this->postJson('/api/v1/auth/app/register', [
            'name' => 'No Verify',
            'email' => 'no-verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(200);

        Mail::assertNotSent(VerifyEmail::class);
    }

    public function test_verify_endpoint_marks_user_as_verified(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();

        $this->getJson($this->signedVerificationUrl($user))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Email verified successfully.');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_verify_endpoint_rejects_invalid_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $tampered = $this->signedVerificationUrl($user).'&extra=1';

        $this->getJson($tampered)->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verify_endpoint_rejects_wrong_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1('attacker@example.com')],
        );

        $this->getJson($url)
            ->assertStatus(404)
            ->assertJsonPath('message', 'Verification link is invalid.');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verify_endpoint_redirects_when_redirect_url_configured(): void
    {
        config()->set('boilerplate.auth.email_verification.redirect_url', 'https://app.test/verified');

        $user = User::factory()->unverified()->create();

        $response = $this->get($this->signedVerificationUrl($user));

        $response->assertRedirect('https://app.test/verified?status=success');
    }

    public function test_resend_endpoint_sends_email_for_unverified_user(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertStatus(200);

        Mail::assertSent(VerifyEmail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_resend_endpoint_says_already_verified_for_verified_user(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Email already verified.');

        Mail::assertNotSent(VerifyEmail::class);
    }

    public function test_resend_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/email/verification-notification')
            ->assertStatus(401);
    }

    public function test_login_blocks_unverified_user_when_required(): void
    {
        config()->set('boilerplate.auth.email_verification.required_for_login', true);

        $user = User::factory()->unverified()->create([
            'email' => 'blocked@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/auth/app/login', [
            'email' => 'blocked@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Email address has not been verified.');
    }

    public function test_login_allows_unverified_user_by_default(): void
    {
        config()->set('boilerplate.auth.email_verification.required_for_login', false);

        $user = User::factory()->unverified()->create([
            'email' => 'allowed@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/auth/app/login', [
            'email' => 'allowed@example.com',
            'password' => 'password123',
        ])->assertStatus(200);
    }

    public function test_otp_verify_marks_email_as_verified(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create([
            'email' => 'otp-user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = app(\App\Services\Otp\Contracts\OtpService::class)->create('otp-user@example.com');

        $this->postJson('/api/v1/auth/app/otp/verify', [
            'email' => 'otp-user@example.com',
            'token' => $token,
        ])->assertStatus(200);

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }
}
