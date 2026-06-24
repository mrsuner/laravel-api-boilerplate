<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

/**
 * @group Internal Admin — Roles & Permissions
 *
 * Read-only views of the RBAC catalog. Roles and permissions are managed via
 * config/boilerplate.php and the RolesAndPermissionsSeeder, not through here.
 */
class AdminRoleController extends Controller
{
    /**
     * List roles
     *
     * Returns every role with its attached permissions.
     *
     * @authenticated
     */
    public function index(): JsonResponse
    {
        return $this->respondOk(Role::query()->with('permissions')->get());
    }

    /**
     * List permissions
     *
     * @authenticated
     */
    public function permissions(): JsonResponse
    {
        return $this->respondOk(Permission::query()->get());
    }
}
