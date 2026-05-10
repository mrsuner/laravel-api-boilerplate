<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePasswordPolicy();
    }

    /**
     * Compose the application-wide Password::defaults() chain from
     * config/boilerplate.php → auth.password. Form requests opt in via
     * `Password::defaults()` instead of a hard-coded `min:` rule.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min((int) config('boilerplate.auth.password.min_length', 8));

            if (config('boilerplate.auth.password.require_mixed_case')) {
                $rule = $rule->mixedCase();
            }

            if (config('boilerplate.auth.password.require_numbers')) {
                $rule = $rule->numbers();
            }

            if (config('boilerplate.auth.password.require_symbols')) {
                $rule = $rule->symbols();
            }

            if (config('boilerplate.auth.password.uncompromised')) {
                $rule = $rule->uncompromised();
            }

            return $rule;
        });
    }
}
