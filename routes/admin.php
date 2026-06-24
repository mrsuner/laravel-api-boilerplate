<?php

use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminConfigController;
use App\Http\Controllers\Admin\AdminHealthController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\InternalIpWhitelist;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

if (! config('boilerplate.admin.enabled', true)) {
    return;
}

Route::prefix('internal/admin/v1')
    ->middleware(['throttle:60,1', InternalIpWhitelist::class])
    ->name('admin.')
    ->group(function () {
        // Public within the internal network — no token required
        Route::post('auth/login', [AdminAuthController::class, 'login'])->name('auth.login');

        // All routes below require a valid admin-scoped Sanctum token
        Route::middleware(['auth:sanctum', CheckForAnyAbility::class.':admin', EnsureAdminAccess::class])
            ->group(function () {
                Route::post('auth/logout', [AdminAuthController::class, 'logout'])->name('auth.logout');
                Route::get('auth/me', [AdminAuthController::class, 'me'])->name('auth.me');

                // Users
                Route::prefix('users')->name('users.')->group(function () {
                    Route::get('/', [AdminUserController::class, 'index'])->name('index');
                    Route::get('/{user}', [AdminUserController::class, 'show'])->name('show');
                    Route::patch('/{user}/ban', [AdminUserController::class, 'ban'])->name('ban');
                    Route::patch('/{user}/unban', [AdminUserController::class, 'unban'])->name('unban');
                    Route::put('/{user}/roles', [AdminUserController::class, 'syncRoles'])->name('roles.sync');
                    Route::post('/{user}/roles/{role}', [AdminUserController::class, 'assignRole'])->name('roles.assign');
                    Route::delete('/{user}/roles/{role}', [AdminUserController::class, 'revokeRole'])->name('roles.revoke');
                    Route::get('/{user}/audit-logs', [AdminAuditLogController::class, 'forUser'])->name('audit-logs');
                });

                // Roles & Permissions — read-only
                Route::get('roles', [AdminRoleController::class, 'index'])->name('roles.index');
                Route::get('permissions', [AdminRoleController::class, 'permissions'])->name('permissions.index');

                // Audit Logs
                Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
                    Route::get('/', [AdminAuditLogController::class, 'index'])->name('index');
                    Route::get('/{log}', [AdminAuditLogController::class, 'show'])->name('show');
                });

                // Health
                Route::get('health', [AdminHealthController::class, 'index'])->name('health');

                // Runtime config
                Route::get('config', [AdminConfigController::class, 'show'])->name('config.show');
                Route::put('config', [AdminConfigController::class, 'update'])->name('config.update');
            });
    });
