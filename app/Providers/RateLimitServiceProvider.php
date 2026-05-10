<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Registers per-endpoint rate limiters consumed by `throttle:auth-<name>`
 * route middleware. Limits are sourced from
 * config/boilerplate.php → auth.rate_limit and re-evaluated per request, so
 * config()->set() in tests takes effect without rebooting the application.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $names = array_keys(config('boilerplate.auth.rate_limit.limits', []));

        foreach ($names as $name) {
            RateLimiter::for("auth-{$name}", function (Request $request) use ($name): Limit {
                return $this->buildLimit($name, $request);
            });
        }
    }

    private function buildLimit(string $name, Request $request): Limit
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
}
