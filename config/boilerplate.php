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
    | API Response Helpers
    |--------------------------------------------------------------------------
    |
    | Defaults consumed by App\Http\Controllers\Controller response helpers
    | (respondOk, respondNotFound, respondForbidden, ...). Messages are run
    | through Laravel's translator, so adding lang/<locale>/boilerplate.php
    | with matching keys will localize them transparently.
    |
    */

    'responses' => [
        // When true and a helper is called with a null message, the matching
        // default below is used. Set false to omit the message key entirely.
        'use_defaults' => true,

        'default_messages' => [
            'ok' => 'Success.',
            'created' => 'Resource created.',
            'accepted' => 'Request accepted.',
            'bad_request' => 'Bad request.',
            'unauthorized' => 'Unauthenticated.',
            'forbidden' => 'This action is unauthorized.',
            'not_found' => 'Resource not found.',
            'method_not_allowed' => 'Method not allowed.',
            'conflict' => 'Resource conflict.',
            'unprocessable' => 'The given data was invalid.',
            'too_many_requests' => 'Too many requests.',
            'server_error' => 'Server error.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Exception Rendering
    |--------------------------------------------------------------------------
    |
    | Controls how framework exceptions are rendered for API requests. When
    | render_for_api is true, common exceptions (Authentication, Authorization,
    | NotFound, MethodNotAllowed, Validation, Throttle, generic Throwable) are
    | converted to a standard JSON envelope matching the response helpers.
    |
    */

    'exceptions' => [
        // Render exceptions as standardized JSON for /api/* and JSON requests.
        'render_for_api' => true,

        // When true, server errors include the exception class, file, line,
        // and message in the response payload. Useful for staging; never
        // enable in production.
        'expose_debug_in_response' => env('API_EXPOSE_DEBUG', false),
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

        // Password policy applied via Illuminate\Validation\Rules\Password.
        // Adjust per project; defaults aim at modern best-practice and can
        // be loosened for testing or legacy migrations.
        'password' => [
            'min_length' => (int) env('AUTH_PASSWORD_MIN_LENGTH', 8),
            'require_mixed_case' => (bool) env('AUTH_PASSWORD_REQUIRE_MIXED_CASE', true),
            'require_numbers' => (bool) env('AUTH_PASSWORD_REQUIRE_NUMBERS', true),
            'require_symbols' => (bool) env('AUTH_PASSWORD_REQUIRE_SYMBOLS', false),
            // Checks the password against haveibeenpwned.com — requires HTTP
            // egress, so leave disabled in tests/dev unless validating.
            'uncompromised' => (bool) env('AUTH_PASSWORD_UNCOMPROMISED', false),
        ],

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

        // Email verification — when enabled, registration triggers a signed
        // verification email and protected flows can require verification.
        // The verify endpoint is mounted at /api/v1/auth/email/verify/{id}/{hash}.
        'email_verification' => [
            'enabled' => (bool) env('AUTH_EMAIL_VERIFICATION_ENABLED', true),
            // When true, login refuses unverified accounts with 403.
            'required_for_login' => (bool) env('AUTH_EMAIL_VERIFICATION_REQUIRED_FOR_LOGIN', false),
            'expire_minutes' => (int) env('AUTH_EMAIL_VERIFICATION_EXPIRE_MINUTES', 60),
            // Optional frontend URL the user is redirected to after a click.
            // ?status=success|failure&reason=... is appended. When null/empty,
            // the controller returns JSON instead.
            'redirect_url' => env('AUTH_EMAIL_VERIFICATION_REDIRECT_URL'),
        ],

        // Per-endpoint rate limits applied via throttle:auth-<name>.
        // Each entry is { max: int, per_minutes: int }. Setting 'enabled' to
        // false bypasses every limiter (useful in tests). Limiters are keyed
        // by the authenticated user id when present, otherwise by client IP.
        'rate_limit' => [
            'enabled' => env('AUTH_RATE_LIMIT_ENABLED', true),
            'limits' => [
                'login' => ['max' => 10, 'per_minutes' => 1],
                'register' => ['max' => 5, 'per_minutes' => 1],
                'otp_issue' => ['max' => 5, 'per_minutes' => 1],
                'otp_verify' => ['max' => 10, 'per_minutes' => 1],
                'password_forgot' => ['max' => 3, 'per_minutes' => 1],
                'social' => ['max' => 10, 'per_minutes' => 1],
                'email_verify_resend' => ['max' => 6, 'per_minutes' => 1],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RBAC (Roles & Permissions)
    |--------------------------------------------------------------------------
    |
    | Defaults consumed by Database\Seeders\RolesAndPermissionsSeeder. Edit
    | the lists for your project's domain — the seeder upserts by name, so
    | running it repeatedly is safe and additive. The default role is auto-
    | assigned to new users by the registration controllers when set.
    |
    | Permission naming follows action.resource (e.g. users.read). Each role
    | lists the permission names it should have; '*' grants every permission.
    |
    */

    'rbac' => [
        // Master switch. When false the seeder is a no-op and registration
        // does not auto-assign a role.
        'enabled' => (bool) env('RBAC_ENABLED', true),

        // Role assigned to newly registered users. Set to null to skip
        // auto-assignment.
        'default_role' => env('RBAC_DEFAULT_ROLE', 'user'),

        'permissions' => [
            ['name' => 'users.read', 'display_name' => 'View Users', 'description' => 'List and view users.'],
            ['name' => 'users.write', 'display_name' => 'Manage Users', 'description' => 'Create, update, and delete users.'],
            ['name' => 'roles.read', 'display_name' => 'View Roles', 'description' => 'List and view roles.'],
            ['name' => 'roles.write', 'display_name' => 'Manage Roles', 'description' => 'Create, update, and delete roles.'],
        ],

        'roles' => [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full access.',
                'permissions' => ['*'],
            ],
            [
                'name' => 'user',
                'display_name' => 'User',
                'description' => 'Standard user.',
                'permissions' => [],
            ],
        ],
    ],

];
