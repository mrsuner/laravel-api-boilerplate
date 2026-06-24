<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_active || ! $user->hasRole('admin') || ! $this->hasExplicitAdminAbility($user->currentAccessToken())) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function hasExplicitAdminAbility(mixed $token): bool
    {
        $ability = (string) config('boilerplate.admin.token_ability', 'admin');

        if ($token instanceof PersonalAccessToken && $token->exists) {
            return in_array($ability, (array) $token->abilities, true);
        }

        return $token?->can($ability) === true;
    }
}
