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

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    // === Registration Tests ===

    public function test_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/web/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user'], 'message']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/web/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // === Login Tests ===

    public function test_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/web/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user'], 'message']);
    }

    public function test_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/web/login', [
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

        $response = $this->postJson('/api/v1/auth/web/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Account is inactive.']);
    }

    public function test_can_access_protected_routes_after_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/web/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJson(['data' => ['id' => $user->id, 'email' => $user->email]]);
    }

    // === OTP Tests ===

    public function test_can_request_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/web/otp', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'OTP sent to your email.']);

        $this->assertDatabaseHas('otps', ['identifier' => 'test@example.com']);
        Mail::assertSent(LoginOtp::class);
    }

    public function test_can_verify_otp_and_establish_session(): void
    {
        Mail::fake();
        $email = 'test@example.com';

        $this->postJson('/api/v1/auth/web/otp', ['email' => $email]);
        $otp = Otp::where('identifier', $email)->first();

        $response = $this->postJson('/api/v1/auth/web/otp/verify', [
            'email' => $email,
            'token' => $otp->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user'], 'message']);

        $this->assertDatabaseMissing('otps', ['id' => $otp->id]);

        // Verify session is established
        $meResponse = $this->getJson('/api/v1/me');
        $meResponse->assertStatus(200);
    }

    public function test_cannot_verify_expired_otp(): void
    {
        Otp::create([
            'identifier' => 'test@example.com',
            'token' => '123456',
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/v1/auth/web/otp/verify', [
            'email' => 'test@example.com',
            'token' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    // === Password Reset Tests ===

    public function test_can_request_password_reset(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/web/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password reset link sent to your email.']);

        Mail::assertSent(PasswordResetLink::class);
    }

    public function test_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/web/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password has been reset successfully.']);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    // === Logout Tests ===

    public function test_can_logout_and_destroy_session(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // Login first
        $this->postJson('/api/v1/auth/web/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // Then logout
        $response = $this->postJson('/api/v1/auth/web/logout');
        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully.']);

        // Refresh the application to clear authentication state
        $this->refreshApplication();

        // Verify cannot access protected routes with fresh request
        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(401);
    }

    // === Change Password Tests ===

    public function test_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
            'is_active' => true,
        ]);

        // Login first
        $this->postJson('/api/v1/auth/web/login', [
            'email' => $user->email,
            'password' => 'oldpassword',
        ]);

        $response = $this->postJson('/api/v1/auth/web/change-password', [
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
            'is_active' => true,
        ]);

        // Login first
        $this->postJson('/api/v1/auth/web/login', [
            'email' => $user->email,
            'password' => 'oldpassword',
        ]);

        $response = $this->postJson('/api/v1/auth/web/change-password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    // === Unauthenticated Access Tests ===

    public function test_cannot_access_protected_routes_without_session(): void
    {
        $response = $this->postJson('/api/v1/auth/web/logout');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/auth/web/change-password');
        $response->assertStatus(401);
    }
}
