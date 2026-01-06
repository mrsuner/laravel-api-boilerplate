<?php

namespace App\Providers;

use App\Services\Otp\CacheDriver;
use App\Services\Otp\Contracts\OtpService;
use App\Services\Otp\DatabaseDriver;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class OtpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(OtpService::class, function ($app) {
            $driver = config('boilerplate.auth.otp_driver', 'database');

            return match ($driver) {
                'database' => new DatabaseDriver,
                'cache' => new CacheDriver,
                default => throw new InvalidArgumentException("Unsupported OTP driver: {$driver}"),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
