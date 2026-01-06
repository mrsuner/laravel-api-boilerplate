<?php

use App\Http\Controllers\Api\Auth\AppAuthController;
use App\Http\Controllers\Api\Auth\AppSocialAuthController;
use App\Http\Controllers\Api\Auth\SharedAuthController;
use App\Http\Controllers\Api\Auth\WebAuthController;
use App\Http\Controllers\Api\Auth\WebSocialAuthController;
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
    Route::post('/register', [AppAuthController::class, 'register']);
    Route::post('/login', [AppAuthController::class, 'login']);
    Route::post('/otp', [AppAuthController::class, 'requestOtp']);
    Route::post('/otp/verify', [AppAuthController::class, 'verifyOtp']);
    Route::post('/forgot-password', [AppAuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AppAuthController::class, 'resetPassword']);

    // Social authentication routes
    Route::prefix('social')->group(function () {
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
    Route::post('/register', [WebAuthController::class, 'register']);
    Route::post('/login', [WebAuthController::class, 'login']);
    Route::post('/otp', [WebAuthController::class, 'requestOtp']);
    Route::post('/otp/verify', [WebAuthController::class, 'verifyOtp']);
    Route::post('/forgot-password', [WebAuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [WebAuthController::class, 'resetPassword']);

    // Social authentication routes
    Route::prefix('social')->group(function () {
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
| Shared Routes (Works with both token and cookie auth)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [SharedAuthController::class, 'me']);
});
