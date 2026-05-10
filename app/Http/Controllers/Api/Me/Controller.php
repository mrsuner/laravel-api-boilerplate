<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Base controller for /me/* endpoints.
 *
 * Each Me\* controller should expose read-only views of resources owned by
 * the authenticated user. Mutation endpoints belong on the canonical resource
 * controller — keep this layer focused on listing and reading.
 */
abstract class Controller extends BaseController
{
    /**
     * Resolve the authenticated user; abort 401 if missing.
     *
     * Routes under the Me\* group are always protected by auth:sanctum, but
     * this guard keeps the controller honest if the middleware is ever lost.
     */
    protected function currentUser(Request $request): Authenticatable
    {
        $user = $request->user();

        abort_if($user === null, 401);

        return $user;
    }

    /**
     * Resolve a per_page query value, clamped to a sane range.
     */
    protected function resolvePerPage(Request $request, int $default = 15, int $max = 100): int
    {
        $value = (int) $request->query('per_page', $default);

        if ($value < 1) {
            return $default;
        }

        return min($value, $max);
    }
}
