<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('boilerplate.rbac.enabled', true)) {
            return;
        }

        $permissions = collect((array) config('boilerplate.rbac.permissions', []))
            ->mapWithKeys(fn (array $permission): array => [
                $permission['name'] => Permission::query()->updateOrCreate(
                    ['name' => $permission['name']],
                    [
                        'display_name' => $permission['display_name'] ?? null,
                        'description' => $permission['description'] ?? null,
                    ],
                ),
            ]);

        foreach ((array) config('boilerplate.rbac.roles', []) as $roleConfig) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleConfig['name']],
                [
                    'display_name' => $roleConfig['display_name'] ?? null,
                    'description' => $roleConfig['description'] ?? null,
                ],
            );

            $names = $roleConfig['permissions'] ?? [];

            $assignedPermissions = in_array('*', $names, true)
                ? $permissions->values()
                : $permissions->only($names)->values();

            $role->syncPermissions($assignedPermissions);
        }
    }
}
