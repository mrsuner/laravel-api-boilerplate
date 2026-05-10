<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $password): TestResponse
    {
        return $this->postJson('/api/v1/auth/app/register', [
            'name' => 'Test User',
            'email' => 'policy-'.uniqid().'@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_min_length_rejects_short_password(): void
    {
        config()->set('boilerplate.auth.password.min_length', 12);

        $this->register('short1234')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_min_length_accepts_long_enough_password(): void
    {
        config()->set('boilerplate.auth.password.min_length', 8);

        $this->register('password123')->assertStatus(200);
    }

    public function test_mixed_case_rejected_when_required(): void
    {
        config()->set('boilerplate.auth.password.require_mixed_case', true);

        $this->register('alllowercase1')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_mixed_case_accepted_when_required(): void
    {
        config()->set('boilerplate.auth.password.require_mixed_case', true);

        $this->register('MixedCase1')->assertStatus(200);
    }

    public function test_numbers_rejected_when_required(): void
    {
        config()->set('boilerplate.auth.password.require_numbers', true);

        $this->register('NoDigitsAtAll')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_symbols_rejected_when_required(): void
    {
        config()->set('boilerplate.auth.password.require_symbols', true);

        $this->register('NoSymbols123')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_symbols_accepted_when_required(): void
    {
        config()->set('boilerplate.auth.password.require_symbols', true);

        $this->register('Strong#123')->assertStatus(200);
    }

    public function test_password_defaults_rule_is_registered(): void
    {
        $this->assertInstanceOf(Password::class, Password::default());
    }
}
