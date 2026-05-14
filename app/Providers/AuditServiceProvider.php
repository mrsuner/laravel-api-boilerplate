<?php

namespace App\Providers;

use App\Services\Audit\AuditLogger;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class, fn (): AuditLogger => new AuditLogger);
    }

    public function boot(): void {}
}
