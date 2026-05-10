<?php

namespace App\Listeners;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

class AssignDefaultRole
{
    public function handle(Registered $event): void
    {
        if (! config('boilerplate.rbac.enabled', true)) {
            return;
        }

        $defaultRole = config('boilerplate.rbac.default_role');

        if ($defaultRole === null || $defaultRole === '') {
            return;
        }

        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        if (! Role::query()->where('name', $defaultRole)->exists()) {
            Log::warning('Default RBAC role not found; skipping assignment.', [
                'role' => $defaultRole,
                'user_id' => $user->getKey(),
            ]);

            return;
        }

        if ($user->hasRole($defaultRole)) {
            return;
        }

        $user->addRole($defaultRole);
    }
}
