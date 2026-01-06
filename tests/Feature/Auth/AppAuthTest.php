<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtp;
use App\Mail\PasswordResetLink;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AppAuthTest extends TestCase
{
    use RefreshDatabase;

    // === Registration Tests ===

    public function test_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/app/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'user']]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/app/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_register_with_weak_password(): void
    {
        $response = $this->postJson('/api/v1/auth/app/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_cannot_register_with_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/app/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // === Login Tests ===

    public function test_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/app/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'user']]);
    }

    public function test_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/app/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_login_when_account_inactive(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Account is inactive.']);
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
            'last_login_at' => null,
        ]);

        $this->postJson('/api/v1/auth/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    // === OTP Tests ===

    public function test_can_request_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/app/otp', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'OTP sent to your email.']);

        $this->assertDatabaseHas('otps', ['identifier' => 'test@example.com']);
        Mail::assertSent(LoginOtp::class);
    }

    public function test_can_verify_otp_and_get_token(): void
    {
        Mail::fake();
        $email = 'test@example.com';

        $this->postJson('/api/v1/auth/app/otp', ['email' => $email]);
        $otp = Otp::where('identifier', $email)->first();

        $response = $this->postJson('/api/v1/auth/app/otp/verify', [
            'email' => $email,
            'token' => $otp->token,
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'user']]);

        $this->assertDatabaseMissing('otps', ['id' => $otp->id]);
    }

    public function test_cannot_verify_expired_otp(): void
    {
        Otp::create([
            'identifier' => 'test@example.com',
            'token' => '123456',
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/v1/auth/app/otp/verify', [
            'email' => 'test@example.com',
            'token' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_cannot_verify_invalid_otp(): void
    {
        $response = $this->postJson('/api/v1/auth/app/otp/verify', [
            'email' => 'test@example.com',
            'token' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    // === Password Reset Tests ===

    public function test_can_request_password_reset(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/app/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password reset link sent to your email.']);

        Mail::assertSent(PasswordResetLink::class);
    }

    public function test_cannot_request_password_reset_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/app/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/app/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password has been reset successfully.']);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_cannot_reset_password_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/app/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
    }

    // === Authenticated Actions ===

    public function test_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/auth/app/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully.']);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/auth/app/change-password', [
                'current_password' => 'oldpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password changed successfully.']);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_cannot_change_password_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/auth/app/change-password', [
                'current_password' => 'wrongpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_cannot_access_protected_routes_without_token(): void
    {
        $response = $this->postJson('/api/v1/auth/app/logout');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/auth/app/change-password');
        $response->assertStatus(401);
    }

    // === Shared Endpoint Tests ===

    public function test_can_get_me_with_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJson(['data' => ['id' => $user->id, 'email' => $user->email]]);
    }

    public function test_cannot_get_me_without_auth(): void
    {
        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(401);
    }
}
