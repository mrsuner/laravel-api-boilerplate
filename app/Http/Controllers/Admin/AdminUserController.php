<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SyncRolesRequest;
use App\Http\Resources\Admin\AdminAuditLogResource;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Internal Admin — Users
 *
 * Management endpoints for the existing users table: listing, banning and
 * role administration. Every mutating action is recorded to the audit trail.
 */
class AdminUserController extends Controller
{
    /**
     * List users
     *
     * @authenticated
     *
     * @queryParam search string Filter by name or email (LIKE). Example: john
     * @queryParam is_active boolean Filter by active status. Example: true
     * @queryParam role string Filter by role name. Example: admin
     * @queryParam per_page integer Results per page (max 100). Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);

        $query = User::query()->with('roles');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($role = $request->string('role')->trim()->value()) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        $users = $query->latest()
            ->paginate($perPage)
            ->through(fn (User $user) => (new AdminUserResource($user))->resolve());

        return $this->respondPaginated($users);
    }

    /**
     * Show user
     *
     * Returns the user with roles plus their 10 most recent audit log entries.
     *
     * @authenticated
     */
    public function show(User $user): JsonResponse
    {
        $user->load('roles');

        $recentLogs = AuditLog::query()
            ->forUser($user->id)
            ->latest()
            ->limit(10)
            ->get();

        return $this->respondOk([
            'user' => AdminUserResource::make($user),
            'recent_audit_logs' => AdminAuditLogResource::collection($recentLogs),
        ]);
    }

    /**
     * Ban user
     *
     * Deactivates the account and revokes all of its Sanctum tokens.
     *
     * @authenticated
     */
    public function ban(User $user): JsonResponse
    {
        if ($user->hasRole('admin')) {
            return $this->respondError(422, 'Cannot ban another admin.');
        }

        $user->is_active = false;
        $user->save();

        $user->tokens()->delete();

        audit_log('admin.user.banned', $user, ['user' => auth()->user()]);

        return $this->respondOk(message: 'User banned.');
    }

    /**
     * Unban user
     *
     * @authenticated
     */
    public function unban(User $user): JsonResponse
    {
        $user->is_active = true;
        $user->save();

        audit_log('admin.user.unbanned', $user, ['user' => auth()->user()]);

        return $this->respondOk(message: 'User unbanned.');
    }

    /**
     * Sync roles
     *
     * Replaces the user's full set of roles.
     *
     * @authenticated
     */
    public function syncRoles(SyncRolesRequest $request, User $user): JsonResponse
    {
        if ($user->hasRole('admin')) {
            return $this->respondError(422, 'Cannot modify admin roles.');
        }

        $roles = $request->validated('roles');

        $user->syncRoles($roles);

        audit_log('admin.user.roles_synced', $user, [
            'metadata' => ['roles' => $roles],
            'user' => auth()->user(),
        ]);

        return $this->respondOk(AdminUserResource::make($user->fresh('roles')));
    }

    /**
     * Assign role
     *
     * @authenticated
     */
    public function assignRole(User $user, string $role): JsonResponse
    {
        if (! Role::query()->where('name', $role)->exists()) {
            return $this->respondNotFound('Role not found.');
        }

        $user->addRole($role);

        audit_log('admin.user.role_assigned', $user, [
            'metadata' => ['role' => $role],
            'user' => auth()->user(),
        ]);

        return $this->respondOk(AdminUserResource::make($user->fresh('roles')));
    }

    /**
     * Revoke role
     *
     * @authenticated
     */
    public function revokeRole(User $user, string $role): JsonResponse
    {
        if ($role === 'admin' && $user->id === auth()->id()) {
            return $this->respondError(422, 'Cannot revoke your own admin role.');
        }

        $user->removeRole($role);

        if ($role === 'admin') {
            $this->revokeAdminTokens($user);
        }

        audit_log('admin.user.role_revoked', $user, [
            'metadata' => ['role' => $role],
            'user' => auth()->user(),
        ]);

        return $this->respondOk(AdminUserResource::make($user->fresh('roles')));
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 20);

        if ($perPage < 1) {
            $perPage = 20;
        }

        return min($perPage, 100);
    }

    private function revokeAdminTokens(User $user): void
    {
        $user->tokens()->get()->each(function ($token): void {
            $ability = (string) config('boilerplate.admin.token_ability', 'admin');

            if (in_array($ability, (array) $token->abilities, true)) {
                $token->delete();
            }
        });
    }
}
