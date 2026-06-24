<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @group Internal Admin — Authentication
 *
 * Issues and revokes admin-scoped Sanctum tokens for the internal admin API.
 */
class AdminAuthController extends Controller
{
    /**
     * Login
     *
     * Authenticate an admin and return an admin-scoped Sanctum token. Failure
     * reasons are deliberately opaque: a non-admin or wrong password both
     * return the same generic "Invalid credentials." message.
     *
     * @unauthenticated
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return $this->respondError(401, 'Invalid credentials.');
        }

        if (! $user->is_active) {
            return $this->respondError(403, 'Account is inactive.');
        }

        if (! $user->hasRole('admin')) {
            return $this->respondError(401, 'Invalid credentials.');
        }

        $user->tokens()->where('name', 'admin-session')->delete();

        $token = $user->createToken(
            'admin-session',
            ['admin'],
            now()->addHours((int) config('boilerplate.admin.token_ttl_hours', 8)),
        );

        audit_log('admin.login', $user, ['metadata' => ['ip' => $request->ip()]]);

        return $this->respondOk([
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => AdminUserResource::make($user->load('roles')),
        ]);
    }

    /**
     * Logout
     *
     * Revoke the current admin session token.
     *
     * @authenticated
     */
    public function logout(Request $request): JsonResponse
    {
        audit_log('admin.logout', $request->user());

        $request->user()->currentAccessToken()->delete();

        return $this->respondOk(message: 'Logged out.');
    }

    /**
     * Me
     *
     * Return the authenticated admin's profile.
     *
     * @authenticated
     */
    public function me(Request $request): JsonResponse
    {
        return $this->respondOk(
            AdminUserResource::make($request->user()->load('roles'))
        );
    }
}
