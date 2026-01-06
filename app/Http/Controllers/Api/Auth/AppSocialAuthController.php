<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Auth\Concerns\HandlesSocialiteAuth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SocialCallbackRequest;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

/**
 * @group App Authentication - Social
 *
 * Token-based social authentication APIs for native mobile/desktop applications
 */
class AppSocialAuthController extends Controller
{
    use HandlesSocialiteAuth;

    /**
     * Get OAuth Redirect URL
     *
     * Get the OAuth redirect URL for the specified provider.
     * The client should open this URL in a browser to start the OAuth flow.
     *
     * @unauthenticated
     *
     * @urlParam provider string required The OAuth provider name. Example: google
     *
     * @response 200 {
     *   "redirect_url": "https://accounts.google.com/o/oauth2/v2/auth?..."
     * }
     * @response 422 scenario="Invalid provider" {
     *   "message": "The given data was invalid.",
     *   "errors": {"provider": ["The selected provider is invalid."]}
     * }
     */
    public function redirect(string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        $url = Socialite::driver($provider)
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return $this->respondOk([
            'redirect_url' => $url,
        ]);
    }

    /**
     * OAuth Callback
     *
     * Exchange the OAuth code for an access token and authenticate the user.
     * Returns a Bearer token for subsequent API requests.
     *
     * @unauthenticated
     *
     * @urlParam provider string required The OAuth provider name. Example: google
     *
     * @bodyParam code string required The OAuth authorization code from the provider. Example: 4/0AfJohXn...
     * @bodyParam state string The state parameter for CSRF protection. Example: xyz123
     * @bodyParam device_name string Device name for the token. Example: iPhone 15 Pro
     *
     * @response 200 {
     *   "access_token": "1|abc123...",
     *   "token_type": "Bearer",
     *   "user": {"id": 1, "name": "John Doe", "email": "john@example.com"}
     * }
     * @response 403 scenario="Account inactive" {
     *   "message": "Account is inactive."
     * }
     * @response 422 scenario="Invalid provider" {
     *   "message": "The given data was invalid.",
     *   "errors": {"provider": ["The selected provider is invalid."]}
     * }
     */
    public function callback(string $provider, SocialCallbackRequest $request): JsonResponse
    {
        $this->validateProvider($provider);

        $socialUser = Socialite::driver($provider)
            ->stateless()
            ->user();

        $user = $this->findOrCreateUser($socialUser, $provider);

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        $user->update(['last_login_at' => now()]);

        $deviceName = $request->input('device_name', 'social_login');
        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->respondOk([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    /**
     * List Linked Accounts
     *
     * Get all social accounts linked to the authenticated user.
     *
     * @authenticated
     *
     * @response 200 {
     *   "accounts": [
     *     {"id": 1, "provider": "google", "provider_email": "john@gmail.com", "name": "John Doe", "avatar": "https://...", "created_at": "2024-01-01T00:00:00.000000Z"}
     *   ]
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function accounts(Request $request): JsonResponse
    {
        $accounts = $request->user()->socialAccounts()
            ->select(['id', 'provider', 'provider_email', 'name', 'avatar', 'created_at'])
            ->get();

        return $this->respondOk(['accounts' => $accounts]);
    }

    /**
     * Link Social Account
     *
     * Link a new social account to the authenticated user.
     * Returns the OAuth redirect URL for the linking flow.
     *
     * @authenticated
     *
     * @urlParam provider string required The OAuth provider name. Example: google
     *
     * @response 200 {
     *   "redirect_url": "https://accounts.google.com/o/oauth2/v2/auth?..."
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 422 scenario="Invalid provider" {
     *   "message": "The given data was invalid.",
     *   "errors": {"provider": ["The selected provider is invalid."]}
     * }
     */
    public function link(string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        $url = Socialite::driver($provider)
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return $this->respondOk([
            'redirect_url' => $url,
        ]);
    }

    /**
     * Complete Link Social Account
     *
     * Complete the social account linking after OAuth callback.
     *
     * @authenticated
     *
     * @urlParam provider string required The OAuth provider name. Example: google
     *
     * @bodyParam code string required The OAuth authorization code from the provider. Example: 4/0AfJohXn...
     *
     * @response 200 {
     *   "account": {"id": 1, "provider": "google", "provider_email": "john@gmail.com", "name": "John Doe"},
     *   "message": "Social account linked successfully."
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 422 scenario="Already linked" {
     *   "message": "This social account is already linked to your account."
     * }
     * @response 422 scenario="Linked to another user" {
     *   "message": "This social account is already linked to another user."
     * }
     */
    public function linkCallback(string $provider, SocialCallbackRequest $request): JsonResponse
    {
        $this->validateProvider($provider);

        $user = $request->user();
        $socialUser = Socialite::driver($provider)
            ->stateless()
            ->user();

        // Check if this social account is already linked to another user
        $existingAccount = $user->socialAccounts()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($existingAccount) {
            return response()->json([
                'message' => 'This social account is already linked to your account.',
            ], 422);
        }

        // Check if this social account is linked to a different user
        $otherAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->where('user_id', '!=', $user->id)
            ->first();

        if ($otherAccount) {
            return response()->json([
                'message' => 'This social account is already linked to another user.',
            ], 422);
        }

        // Create the social account link
        $account = $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_email' => $socialUser->getEmail(),
            'name' => $socialUser->getName(),
            'avatar' => $socialUser->getAvatar(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'token_expires_at' => isset($socialUser->expiresIn)
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
        ]);

        return $this->respondOk(
            data: ['account' => $account],
            message: 'Social account linked successfully.'
        );
    }

    /**
     * Unlink Social Account
     *
     * Remove a linked social account from the authenticated user.
     *
     * @authenticated
     *
     * @urlParam provider string required The OAuth provider name. Example: google
     *
     * @response 200 {
     *   "message": "Social account unlinked successfully."
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 404 scenario="Not found" {
     *   "message": "Social account not found."
     * }
     * @response 422 scenario="Last auth method" {
     *   "message": "Cannot unlink the last authentication method. Please set a password first."
     * }
     */
    public function unlink(string $provider, Request $request): JsonResponse
    {
        $user = $request->user();

        $account = $user->socialAccounts()
            ->where('provider', $provider)
            ->first();

        if (! $account) {
            return response()->json([
                'message' => 'Social account not found.',
            ], 404);
        }

        // Prevent unlinking if it's the only auth method and user has no password
        $hasPassword = $user->password && ! Hash::check('', $user->password);
        $socialAccountCount = $user->socialAccounts()->count();

        if ($socialAccountCount <= 1 && ! $hasPassword) {
            return response()->json([
                'message' => 'Cannot unlink the last authentication method. Please set a password first.',
            ], 422);
        }

        $account->delete();

        return $this->respondOk(message: 'Social account unlinked successfully.');
    }
}
