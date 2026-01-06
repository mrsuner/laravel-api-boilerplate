<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Boilerplate Features & Modules
    |--------------------------------------------------------------------------
    |
    | This configuration determines which "starter" features are active.
    | You can use this to toggle entire modules on or off when setting up
    | a new project based on this template.
    |
    */

    'modules' => [
        // Handles user registration, login, password reset, etc.
        'auth' => true,

        // Manages roles and permissions (via Laratrust)
        'permissions' => true,

        // Example: User profile management (avatar, bio, etc.)
        'profile' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */

    'api' => [
        // Whether to enforce strictly JSON responses for all API routes
        'force_json_response' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure authentication methods, OTP settings, and password policies.
    |
    */

    'auth' => [
        // Authentication methods toggle
        'password_auth_enabled' => true,
        'otp_auth_enabled' => true,

        // OTP settings
        'otp_length' => 6,
        'otp_expiry_minutes' => 10,
        'otp_driver' => env('OTP_DRIVER', 'database'), // 'database' or 'cache'
        'otp_cache_store' => env('OTP_CACHE_STORE'), // cache store name when using 'cache' driver (null = default)

        // Password reset settings
        'password_reset_expiry_minutes' => 60,

        // Password requirements
        'password_min_length' => 8,

        // Frontend URLs for password reset emails
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'password_reset_url' => env('PASSWORD_RESET_URL', '/reset-password'),

        // Socialite settings
        'socialite_enabled' => env('SOCIALITE_ENABLED', true),
        'socialite_providers' => [
            'google' => env('SOCIALITE_GOOGLE_ENABLED', false),
            'github' => env('SOCIALITE_GITHUB_ENABLED', false),
            'facebook' => env('SOCIALITE_FACEBOOK_ENABLED', false),
            'twitter' => env('SOCIALITE_TWITTER_ENABLED', false),
        ],
        'socialite_callback_url' => env('SOCIALITE_CALLBACK_URL', 'http://localhost:3000/auth/callback'),

        // Auth event notifications
        'notifications' => [
            'welcome_email_enabled' => env('AUTH_WELCOME_EMAIL_ENABLED', true),
            'login_notification_enabled' => env('AUTH_LOGIN_NOTIFICATION_ENABLED', true),
            'logout_notification_enabled' => env('AUTH_LOGOUT_NOTIFICATION_ENABLED', false),
            'password_reset_confirmation_enabled' => env('AUTH_PASSWORD_RESET_CONFIRMATION_ENABLED', true),
        ],
    ],

];
