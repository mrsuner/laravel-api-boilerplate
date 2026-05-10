# Auth Rate Limiting

Per-endpoint named rate limiters protecting authentication flows from brute-force and abuse. Driven entirely by `config/boilerplate.php`, applied via Laravel's standard `throttle:` middleware.

## Endpoints Covered

| Limiter | Routes | Default |
|---|---|---|
| `auth-login` | `POST /auth/{app,web}/login` | 10 / minute |
| `auth-register` | `POST /auth/{app,web}/register` | 5 / minute |
| `auth-otp_issue` | `POST /auth/{app,web}/otp` | 5 / minute |
| `auth-otp_verify` | `POST /auth/{app,web}/otp/verify` | 10 / minute |
| `auth-password_forgot` | `POST /auth/{app,web}/forgot-password` | 3 / minute |
| `auth-social` | `POST /auth/{app,web}/social/{provider}/{redirect,callback}` | 10 / minute |
| `auth-email_verify_resend` | `POST /auth/email/verification-notification` | 6 / minute |

App-token and Web-cookie routes share the same bucket per limiter — they're the same logical operation.

## Bucket Key

```
<limiter_name>|<user_id>     # when authenticated (e.g. resend-verification)
<limiter_name>|<client_ip>   # otherwise
```

## Configuration

```php
'auth' => [
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
```

`AUTH_RATE_LIMIT_ENABLED=false` bypasses every limiter at runtime — limiters re-read config per request, so this also works via `config()->set(...)` in tests without rebooting the app.

## Throttle Response

Exceeded limits return:

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 60
Content-Type: application/json

{ "message": "Too many requests." }
```

The 429 envelope is produced by the [API exception renderer](api-responses.md), not the throttle middleware — so the message is configurable in the same way as other responses.

## Adding a New Limiter

Two steps:

1. Register a limit in `config/boilerplate.php`:
   ```php
   'limits' => [
       // ...
       'export_pdf' => ['max' => 2, 'per_minutes' => 5],
   ],
   ```
2. Apply the middleware:
   ```php
   Route::post('/exports/pdf', ...)->middleware('throttle:auth-export_pdf');
   ```

`RateLimitServiceProvider` auto-registers a limiter for every key in `limits`, prefixed with `auth-`. No further wiring required.

If you want a different prefix or a fully custom rate limiter (e.g. by user role), define it directly in your own service provider via `RateLimiter::for(...)`.

## Key Files

| File | Purpose |
|---|---|
| `app/Providers/RateLimitServiceProvider.php` | Auto-registers limiters from config. |
| `config/boilerplate.php` → `auth.rate_limit` | Limit values. |
| `routes/api.php` | `throttle:auth-*` middleware on auth routes. |
