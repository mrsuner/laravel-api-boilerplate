<?php

use App\Http\Controllers\Api\Auth\AppAuthController;
use App\Http\Controllers\Api\Auth\AppSocialAuthController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Auth\SharedAuthController;
use App\Http\Controllers\Api\Auth\WebAuthController;
use App\Http\Controllers\Api\Auth\WebSocialAuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\Me\DeviceController as MeDeviceController;
use App\Http\Controllers\Api\Me\FileController as MeFileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| App Authentication Routes (Token-based)
|--------------------------------------------------------------------------
|
| These routes are for native mobile/desktop applications that use
| Bearer token authentication via Laravel Sanctum.
|
*/

Route::prefix('auth/app')->group(function () {
    // Public routes
    Route::post('/register', [AppAuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/login', [AppAuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/otp', [AppAuthController::class, 'requestOtp'])->middleware('throttle:auth-otp_issue');
    Route::post('/otp/verify', [AppAuthController::class, 'verifyOtp'])->middleware('throttle:auth-otp_verify');
    Route::post('/forgot-password', [AppAuthController::class, 'forgotPassword'])->middleware('throttle:auth-password_forgot');
    Route::post('/reset-password', [AppAuthController::class, 'resetPassword']);

    // Social authentication routes
    Route::prefix('social')->middleware('throttle:auth-social')->group(function () {
        Route::post('/{provider}/redirect', [AppSocialAuthController::class, 'redirect']);
        Route::post('/{provider}/callback', [AppSocialAuthController::class, 'callback']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AppAuthController::class, 'logout']);
        Route::post('/change-password', [AppAuthController::class, 'changePassword']);

        // Social account management
        Route::get('/social/accounts', [AppSocialAuthController::class, 'accounts']);
        Route::post('/social/{provider}/link', [AppSocialAuthController::class, 'link']);
        Route::post('/social/{provider}/link/callback', [AppSocialAuthController::class, 'linkCallback']);
        Route::delete('/social/{provider}/unlink', [AppSocialAuthController::class, 'unlink']);
    });
});

/*
|--------------------------------------------------------------------------
| Web Authentication Routes (Cookie-based / SPA)
|--------------------------------------------------------------------------
|
| These routes are for Single Page Applications (SPA) that use
| cookie-based session authentication via Laravel Sanctum.
|
*/

Route::prefix('auth/web')->group(function () {
    // Public routes
    Route::post('/register', [WebAuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/login', [WebAuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/otp', [WebAuthController::class, 'requestOtp'])->middleware('throttle:auth-otp_issue');
    Route::post('/otp/verify', [WebAuthController::class, 'verifyOtp'])->middleware('throttle:auth-otp_verify');
    Route::post('/forgot-password', [WebAuthController::class, 'forgotPassword'])->middleware('throttle:auth-password_forgot');
    Route::post('/reset-password', [WebAuthController::class, 'resetPassword']);

    // Social authentication routes
    Route::prefix('social')->middleware('throttle:auth-social')->group(function () {
        Route::post('/{provider}/redirect', [WebSocialAuthController::class, 'redirect']);
        Route::post('/{provider}/callback', [WebSocialAuthController::class, 'callback']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [WebAuthController::class, 'logout']);
        Route::post('/change-password', [WebAuthController::class, 'changePassword']);

        // Social account management
        Route::get('/social/accounts', [WebSocialAuthController::class, 'accounts']);
        Route::post('/social/{provider}/link', [WebSocialAuthController::class, 'link']);
        Route::post('/social/{provider}/link/callback', [WebSocialAuthController::class, 'linkCallback']);
        Route::delete('/social/{provider}/unlink', [WebSocialAuthController::class, 'unlink']);
    });
});

/*
|--------------------------------------------------------------------------
| Email Verification (Shared)
|--------------------------------------------------------------------------
|
| The verify endpoint is signed-URL protected and does NOT require auth so
| users can click the link from a browser without an active session. Resend
| is auth-protected and rate-limited.
|
*/

Route::prefix('auth/email')->group(function () {
    Route::get('/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware(['auth:sanctum', 'throttle:auth-email_verify_resend']);
});

/*
|--------------------------------------------------------------------------
| Me — Current User & Owned Resources
|--------------------------------------------------------------------------
|
| Endpoints scoped to the authenticated user. /me returns the user identity;
| nested resources (e.g. /me/files) return collections owned by the caller.
| Add new owned-resource controllers under App\Http\Controllers\Api\Me and
| register them inside this group so the auth + scoping pattern stays uniform.
|
*/

Route::middleware('auth:sanctum')->prefix('me')->name('me.')->group(function (): void {
    Route::get('/', [SharedAuthController::class, 'me'])->name('show');
    Route::get('/files', [MeFileController::class, 'index'])->name('files.index');
    Route::get('/devices', [MeDeviceController::class, 'index'])->name('devices.index');
});

/*
|--------------------------------------------------------------------------
| Devices (Push Notifications)
|--------------------------------------------------------------------------
|
| Device token registry for push notifications (FCM out of the box; the
| schema is provider-agnostic). Registration is idempotent and keyed by the
| globally unique push token so a recycled device transfers ownership.
| Listing owned devices lives under /me/devices (read-only Me\* layer).
|
*/

Route::middleware('auth:sanctum')->prefix('devices')->name('devices.')->group(function (): void {
    Route::post('/', [DeviceController::class, 'store'])->name('store');
    Route::delete('/{device}', [DeviceController::class, 'destroy'])->name('destroy');
});

/*
|--------------------------------------------------------------------------
| Files
|--------------------------------------------------------------------------
|
| Upload allows anonymous traffic when boilerplate.files.allow_anonymous_upload
| is enabled — register that route without auth and rely on a stricter throttle.
| Otherwise upload requires Sanctum auth. show/download/destroy are always
| auth-protected.
|
*/

Route::prefix('files')->name('files.')->group(function (): void {
    // Auth gate is enforced inside FileController::store so the route can
    // serve both authenticated and anonymous uploads based on config without
    // re-registering at request time. Throttle limit branches on user presence.
    Route::post('/', [FileController::class, 'store'])
        ->middleware('throttle:files-upload')
        ->name('store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/{file}', [FileController::class, 'show'])->name('show');
        Route::get('/{file}/download', [FileController::class, 'download'])->name('download');
        Route::delete('/{file}', [FileController::class, 'destroy'])->name('destroy');
    });
});
