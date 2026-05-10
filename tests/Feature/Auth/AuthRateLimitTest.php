<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.auth.rate_limit.enabled', true);
        Cache::flush();
    }

    public function test_login_endpoint_returns_429_after_limit(): void
    {
        config()->set('boilerplate.auth.rate_limit.limits.login', ['max' => 2, 'per_minutes' => 1]);

        $payload = ['email' => 'nope@example.com', 'password' => 'password'];

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/auth/app/login', $payload)->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/app/login', $payload)
            ->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertHeader('Retry-After');
    }

    public function test_register_endpoint_returns_429_after_limit(): void
    {
        config()->set('boilerplate.auth.rate_limit.limits.register', ['max' => 1, 'per_minutes' => 1]);

        $this->postJson('/api/v1/auth/app/register', [
            'name' => 'A', 'email' => 'a@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertStatus(200);

        $this->postJson('/api/v1/auth/app/register', [
            'name' => 'B', 'email' => 'b@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertStatus(429);
    }

    public function test_otp_issue_endpoint_returns_429_after_limit(): void
    {
        config()->set('boilerplate.auth.rate_limit.limits.otp_issue', ['max' => 1, 'per_minutes' => 1]);

        $this->postJson('/api/v1/auth/app/otp', ['email' => 'a@example.com'])->assertStatus(200);
        $this->postJson('/api/v1/auth/app/otp', ['email' => 'a@example.com'])->assertStatus(429);
    }

    public function test_password_forgot_endpoint_returns_429_after_limit(): void
    {
        config()->set('boilerplate.auth.rate_limit.limits.password_forgot', ['max' => 1, 'per_minutes' => 1]);

        User::factory()->create(['email' => 'a@example.com', 'password' => Hash::make('password123')]);

        $this->postJson('/api/v1/auth/app/forgot-password', ['email' => 'a@example.com'])->assertStatus(200);
        $this->postJson('/api/v1/auth/app/forgot-password', ['email' => 'a@example.com'])->assertStatus(429);
    }

    public function test_app_and_web_share_the_same_login_bucket(): void
    {
        config()->set('boilerplate.auth.rate_limit.limits.login', ['max' => 2, 'per_minutes' => 1]);

        $payload = ['email' => 'nope@example.com', 'password' => 'password'];

        $this->postJson('/api/v1/auth/app/login', $payload)->assertStatus(422);
        $this->postJson('/api/v1/auth/web/login', $payload)->assertStatus(422);

        // Third hit on either guard hits the shared bucket.
        $this->postJson('/api/v1/auth/web/login', $payload)->assertStatus(429);
    }

    public function test_rate_limit_is_bypassed_when_disabled(): void
    {
        config()->set('boilerplate.auth.rate_limit.enabled', false);
        config()->set('boilerplate.auth.rate_limit.limits.login', ['max' => 1, 'per_minutes' => 1]);

        $payload = ['email' => 'nope@example.com', 'password' => 'password'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/app/login', $payload)->assertStatus(422);
        }
    }

    public function test_throttle_response_uses_standard_envelope(): void
    {
        config()->set('boilerplate.auth.rate_limit.limits.login', ['max' => 1, 'per_minutes' => 1]);

        $payload = ['email' => 'nope@example.com', 'password' => 'password'];

        $this->postJson('/api/v1/auth/app/login', $payload);
        $response = $this->postJson('/api/v1/auth/app/login', $payload);

        $response->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertHeader('Retry-After');
    }
}
