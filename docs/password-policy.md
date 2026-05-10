# Password Policy

Configurable strong-password rules applied via Laravel's `Password::defaults()` to every form that accepts a new password.

## Forms Affected

| Form Request | Endpoint |
|---|---|
| `RegisterRequest` | `POST /auth/{app,web}/register` |
| `ResetPasswordRequest` | `POST /auth/{app,web}/reset-password` |
| `ChangePasswordRequest` | `POST /auth/{app,web}/change-password` |

These use `Password::defaults()` instead of an inline `min:` rule — change config and every form picks it up.

## Configuration

```php
'auth' => [
    'password' => [
        'min_length' => env('AUTH_PASSWORD_MIN_LENGTH', 8),
        'require_mixed_case' => env('AUTH_PASSWORD_REQUIRE_MIXED_CASE', true),
        'require_numbers' => env('AUTH_PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('AUTH_PASSWORD_REQUIRE_SYMBOLS', false),
        'uncompromised' => env('AUTH_PASSWORD_UNCOMPROMISED', false),
    ],
],
```

`uncompromised` checks the password against haveibeenpwned.com. It requires HTTP egress, so leave it off in dev / CI and turn it on only in environments that can reach the internet.

## Behavior

The chain is composed in `AppServiceProvider::boot()`:

```php
Password::defaults(function (): Password {
    $rule = Password::min((int) config('boilerplate.auth.password.min_length'));

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
```

Each form request rebuilds the rule from current config when validation runs, so changes take effect without rebooting.

## Customizing per Form

Use the rule directly when a specific form needs different behavior:

```php
public function rules(): array
{
    return [
        // ...
        'password' => ['required', Password::min(12)->symbols(), 'confirmed'],
    ];
}
```

## Validation Errors

Failures use the standard envelope from the [API responses module](api-responses.md):

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "password": [
      "The password must contain at least one uppercase and one lowercase letter.",
      "The password must contain at least one number."
    ]
  }
}
```

## Key Files

| File | Purpose |
|---|---|
| `app/Providers/AppServiceProvider.php` | Composes `Password::defaults()` from config. |
| `config/boilerplate.php` → `auth.password` | Policy knobs. |
| `app/Http/Requests/Auth/{Register,ResetPassword,ChangePassword}Request.php` | Use `Password::defaults()`. |
