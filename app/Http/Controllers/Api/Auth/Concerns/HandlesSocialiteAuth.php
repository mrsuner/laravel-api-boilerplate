<?php

namespace App\Http\Controllers\Api\Auth\Concerns;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait HandlesSocialiteAuth
{
    /**
     * Validate that the provider is configured and enabled.
     */
    protected function validateProvider(string $provider): void
    {
        if (! config('boilerplate.auth.socialite_enabled')) {
            throw new NotFoundHttpException('Social authentication is disabled.');
        }

        $providers = config('boilerplate.auth.socialite_providers', []);

        if (! isset($providers[$provider]) || ! $providers[$provider]) {
            throw new NotFoundHttpException("Provider [{$provider}] is not enabled.");
        }
    }

    /**
     * Get the list of enabled providers.
     *
     * @return array<string>
     */
    protected function getEnabledProviders(): array
    {
        $providers = config('boilerplate.auth.socialite_providers', []);

        return array_keys(array_filter($providers));
    }

    /**
     * Find or create a user from the Socialite user data.
     */
    protected function findOrCreateUser(SocialiteUser $socialUser, string $provider): User
    {
        // First, check if we have an existing social account
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            // Update the tokens
            $this->updateSocialAccountTokens($socialAccount, $socialUser);

            return $socialAccount->user;
        }

        // No existing social account, check if user with same email exists
        $email = $socialUser->getEmail();
        $user = $email ? User::where('email', $email)->first() : null;

        if (! $user) {
            // Create a new user
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? explode('@', $email ?? 'user')[0],
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'avatar_url' => $socialUser->getAvatar(),
                'is_active' => true,
            ]);
        }

        // Create the social account link
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_email' => $email,
            'name' => $socialUser->getName(),
            'avatar' => $socialUser->getAvatar(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'token_expires_at' => isset($socialUser->expiresIn)
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
        ]);

        // Update avatar if user doesn't have one
        if (! $user->avatar_url && $socialUser->getAvatar()) {
            $user->update(['avatar_url' => $socialUser->getAvatar()]);
        }

        return $user;
    }

    /**
     * Update the social account tokens.
     */
    protected function updateSocialAccountTokens(SocialAccount $socialAccount, SocialiteUser $socialUser): void
    {
        $socialAccount->update([
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken ?? $socialAccount->refresh_token,
            'token_expires_at' => isset($socialUser->expiresIn)
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
        ]);
    }
}
