<?php

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class WebSocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable GitHub provider for testing
        config(['boilerplate.auth.socialite_enabled' => true]);
        config(['boilerplate.auth.socialite_providers.github' => true]);
    }

    // === Redirect Tests ===

    public function test_can_get_redirect_url_for_enabled_provider(): void
    {
        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturn(Mockery::mock([
                'stateless' => Mockery::mock([
                    'redirect' => Mockery::mock([
                        'getTargetUrl' => 'https://github.com/login/oauth/authorize?client_id=test',
                    ]),
                ]),
            ]));

        $response = $this->postJson('/api/v1/auth/web/social/github/redirect');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['redirect_url']]);
    }

    public function test_cannot_get_redirect_url_for_disabled_provider(): void
    {
        config(['boilerplate.auth.socialite_providers.google' => false]);

        $response = $this->postJson('/api/v1/auth/web/social/google/redirect');

        $response->assertStatus(404);
    }

    // === Callback Tests ===

    public function test_callback_creates_new_user_and_establishes_session(): void
    {
        $this->mockSocialiteUser('github', [
            'id' => '12345',
            'name' => 'GitHub User',
            'email' => 'github@example.com',
            'avatar' => 'https://avatars.githubusercontent.com/u/12345',
        ]);

        $response = $this->postJson('/api/v1/auth/web/social/github/callback', [
            'code' => 'test-code',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user'], 'message']);

        $this->assertDatabaseHas('users', ['email' => 'github@example.com']);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'github',
            'provider_id' => '12345',
        ]);

        // Verify session is established
        $meResponse = $this->getJson('/api/v1/me');
        $meResponse->assertStatus(200);
    }

    public function test_callback_links_existing_user_with_same_email(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'is_active' => true,
        ]);

        $this->mockSocialiteUser('github', [
            'id' => '67890',
            'name' => 'Existing User',
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/web/social/github/callback', [
            'code' => 'test-code',
        ]);

        $response->assertStatus(200);

        $this->assertCount(1, User::all());
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => '67890',
        ]);
    }

    public function test_callback_returns_existing_social_account_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => '12345',
            'provider_email' => $user->email,
            'name' => 'GitHub User',
        ]);

        $this->mockSocialiteUser('github', [
            'id' => '12345',
            'name' => 'GitHub User',
            'email' => $user->email,
        ]);

        $response = $this->postJson('/api/v1/auth/web/social/github/callback', [
            'code' => 'test-code',
        ]);

        $response->assertStatus(200)
            ->assertJson(['data' => ['user' => ['id' => $user->id]]]);

        $this->assertCount(1, User::all());
        $this->assertCount(1, SocialAccount::all());
    }

    public function test_callback_rejects_inactive_user(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => '12345',
            'provider_email' => $user->email,
        ]);

        $this->mockSocialiteUser('github', [
            'id' => '12345',
            'email' => 'inactive@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/web/social/github/callback', [
            'code' => 'test-code',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Account is inactive.']);
    }

    // === Session Persistence Tests ===

    public function test_session_persists_after_callback(): void
    {
        $this->mockSocialiteUser('github', [
            'id' => '12345',
            'name' => 'GitHub User',
            'email' => 'github@example.com',
        ]);

        $this->postJson('/api/v1/auth/web/social/github/callback', [
            'code' => 'test-code',
        ]);

        // Make multiple requests to verify session
        $response1 = $this->getJson('/api/v1/me');
        $response1->assertStatus(200);

        $response2 = $this->getJson('/api/v1/me');
        $response2->assertStatus(200);
    }

    // === Account Management Tests ===

    public function test_can_list_linked_accounts(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => '12345',
            'name' => 'GitHub User',
        ]);

        // Login first
        $this->postJson('/api/v1/auth/web/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response = $this->getJson('/api/v1/auth/web/social/accounts');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['accounts']])
            ->assertJsonCount(1, 'data.accounts');
    }

    public function test_can_link_new_social_account(): void
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

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturn(Mockery::mock([
                'stateless' => Mockery::mock([
                    'redirect' => Mockery::mock([
                        'getTargetUrl' => 'https://github.com/login/oauth/authorize',
                    ]),
                ]),
            ]));

        $response = $this->postJson('/api/v1/auth/web/social/github/link');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['redirect_url']]);
    }

    public function test_can_complete_link_social_account(): void
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

        $this->mockSocialiteUser('github', [
            'id' => '99999',
            'name' => 'New GitHub Account',
            'email' => 'newemail@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/web/social/github/link/callback', [
            'code' => 'test-code',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Social account linked successfully.']);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => '99999',
        ]);
    }

    public function test_can_unlink_social_account(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => '12345',
        ]);

        // Login first
        $this->postJson('/api/v1/auth/web/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response = $this->deleteJson('/api/v1/auth/web/social/github/unlink');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Social account unlinked successfully.']);

        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'github',
        ]);
    }

    // === Unauthenticated Access Tests ===

    public function test_cannot_access_protected_routes_without_session(): void
    {
        $response = $this->getJson('/api/v1/auth/web/social/accounts');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/auth/web/social/github/link');
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/v1/auth/web/social/github/unlink');
        $response->assertStatus(401);
    }

    // === Helper Methods ===

    /**
     * Mock the Socialite driver to return a fake user.
     *
     * @param  array<string, mixed>  $userData
     */
    protected function mockSocialiteUser(string $provider, array $userData): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($userData['id'] ?? '12345');
        $socialiteUser->shouldReceive('getName')->andReturn($userData['name'] ?? 'Test User');
        $socialiteUser->shouldReceive('getEmail')->andReturn($userData['email'] ?? 'test@example.com');
        $socialiteUser->shouldReceive('getNickname')->andReturn($userData['nickname'] ?? null);
        $socialiteUser->shouldReceive('getAvatar')->andReturn($userData['avatar'] ?? null);
        $socialiteUser->token = $userData['token'] ?? 'mock-token';
        $socialiteUser->refreshToken = $userData['refresh_token'] ?? null;
        $socialiteUser->expiresIn = $userData['expires_in'] ?? 3600;

        Socialite::shouldReceive('driver')
            ->with($provider)
            ->andReturn(Mockery::mock([
                'stateless' => Mockery::mock([
                    'user' => $socialiteUser,
                ]),
            ]));
    }
}
