<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Registers per-endpoint rate limiters for the boilerplate's auth and files
 * surfaces. Limits are sourced from config/boilerplate.php and re-evaluated
 * per request, so config()->set() in tests takes effect without rebooting.
 *
 * - `auth-<name>`             — per-endpoint auth throttles
 * - `files-upload`            — authenticated upload throttle
 * - `files-anonymous-upload`  — anonymous upload throttle (when enabled)
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerAuthLimiters();
        $this->registerFilesLimiters();
    }

    private function registerAuthLimiters(): void
    {
        $names = array_keys((array) config('boilerplate.auth.rate_limit.limits', []));

        foreach ($names as $name) {
            RateLimiter::for("auth-{$name}", function (Request $request) use ($name): Limit {
                return $this->buildAuthLimit($name, $request);
            });
        }
    }

    private function buildAuthLimit(string $name, Request $request): Limit
    {
        if (! config('boilerplate.auth.rate_limit.enabled', true)) {
            return Limit::none();
        }

        $config = config("boilerplate.auth.rate_limit.limits.{$name}");

        if (! is_array($config) || ! isset($config['max'], $config['per_minutes'])) {
            return Limit::none();
        }

        $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

        return Limit::perMinutes((int) $config['per_minutes'], (int) $config['max'])
            ->by($name.'|'.$key);
    }

    private function registerFilesLimiters(): void
    {
        // Single limiter that branches on auth state — anonymous traffic is
        // typically rate-limited tighter than authenticated traffic.
        RateLimiter::for('files-upload', function (Request $request): Limit {
            $user = $request->user() ?: auth('sanctum')->user();
            $mode = $user !== null ? 'authenticated' : 'anonymous';

            return $this->buildFilesLimit($mode, $request, $user);
        });
    }

    private function buildFilesLimit(string $mode, Request $request, mixed $user): Limit
    {
        if (! config('boilerplate.files.rate_limit.enabled', true)) {
            return Limit::none();
        }

        $config = config("boilerplate.files.rate_limit.{$mode}");

        if (! is_array($config) || ! isset($config['max'], $config['per_minutes'])) {
            return Limit::none();
        }

        $key = $user?->getAuthIdentifier() ?: $request->ip();

        return Limit::perMinutes((int) $config['per_minutes'], (int) $config['max'])
            ->by("files-{$mode}|".$key);
    }
}
