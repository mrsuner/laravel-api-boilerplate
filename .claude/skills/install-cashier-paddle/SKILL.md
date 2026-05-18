---
name: install-cashier-paddle
description: Scaffold Laravel Cashier Paddle into this API boilerplate following its existing conventions. Use when the developer wants Paddle (Paddle Billing) subscriptions. Installs laravel/cashier-paddle, ULID-fixes the published migrations (customers/subscriptions/transactions), adds the Billable trait, wires the built-in Paddle webhook (CSRF/auth-exempt), and scaffolds a versioned Sanctum-protected subscription + checkout API (controllers, Form Requests, Resource, routes) plus PHPUnit tests. Mutually exclusive with install-cashier-stripe.
---

# Install Laravel Cashier Paddle

This boilerplate ships **no billing scaffold**. This skill installs Cashier
Paddle (Paddle Billing) and wires a subscription API matching the project's
conventions (versioned `api/v1` routes, `Me\*` read controllers, base
`Controller` responders, array-rule Form Requests, `JsonResource`s, ULID
models, PHPUnit + `RefreshDatabase`). Webhooks use **Cashier's built-in
controller** — we do not hand-roll event handling.

Unlike Stripe, Paddle Billing is **checkout-driven**: there is no server-side
card collection. Subscriptions are created by the client completing a Paddle
checkout/overlay; the server issues the checkout and reacts via webhook.

## Preconditions

- Confirm Cashier Paddle is not already installed:
  `grep '"laravel/cashier-paddle"' composer.json`. If present, stop and tell
  the developer Paddle billing is already wired.
- **Mutually exclusive with Stripe.** Run `grep '"laravel/cashier"' composer.json`
  (Stripe Cashier). If present, stop — a project should bill through one
  provider. Ask the developer to remove Stripe Cashier first or reconsider.
- `app/Models/User.php` must still use `HasUlids` (the ULID-fix step depends
  on it). If User was changed to integer keys, ask the developer how they want
  the billing tables keyed before proceeding.

## Steps

### 1. Confirm scope with the developer

Ask which Paddle environment (sandbox vs production) and confirm subscriptions
are created via Paddle's hosted/overlay checkout (the API returns a checkout
payload; it does not take card data). Do not invent Paddle price IDs — the API
takes the `price` (Paddle price ID) in the request body.

### 2. Install the package

```
composer require laravel/cashier-paddle --no-interaction
```

Laravel 12 resolves Cashier Paddle v2+ (Paddle Billing). Do not pin unless
composer fails; if it does, retry with `"laravel/cashier-paddle:^2.0"`.

### 3. Publish migrations and ULID-fix them (CRITICAL — boilerplate-specific)

```
php artisan vendor:publish --tag="cashier-migrations" --no-interaction
```

Cashier Paddle's published migrations assume **bigint** billable keys. This
project's `users` table uses **ULID** (`HasUlids`). Before `migrate`, open
every freshly published Cashier migration and fix the key types:

- The `customers` table keys the billable. Cashier publishes
  `$table->foreignId('billable_id')` (or `morphs('billable')`). Change it to
  the ULID-correct form: `$table->foreignUlid('billable_id')` if it is a
  direct user FK, or `$table->ulidMorphs('billable')` if it is polymorphic —
  match whichever shape the published file actually uses. Add the project's
  explicit naming where you touch a key/index (see
  `database/migrations/*_create_user_devices_table.php` for the
  `fk_/idx_/uq_` pattern).
- `subscriptions`, `subscription_items`, and `transactions` reference the
  customer/billable — ensure any `*_id` that points at a ULID table is
  `foreignUlid` (or `ulidMorphs`), not `foreignId`/`morphs`.
- Do **not** otherwise rewrite Cashier's internal column set.

Then:

```
php artisan migrate --no-interaction
```

If `migrate` fails on a foreign-key/type error, the ULID fix above was
incomplete — re-check before continuing.

### 4. Add the Billable trait to User

In `app/Models/User.php`, add `use Laravel\Paddle\Billable;` and include
`Billable` in the class's `use` trait list, alongside the existing
`HasApiTokens, HasFactory, HasRolesAndPermissions, HasUlids, Notifiable`.
Match the existing import ordering and trait-list style. Do not change the
`casts()` method or interfaces.

### 5. Environment variables

Add to `.env` and `.env.example` (leave example values blank):

```
PADDLE_SELLER_ID=
PADDLE_API_KEY=
PADDLE_CLIENT_SIDE_TOKEN=
PADDLE_WEBHOOK_SECRET=
PADDLE_SANDBOX=true
```

These are read by Cashier Paddle's own `config/cashier.php` (publish only if
the developer needs to customize:
`php artisan vendor:publish --tag="cashier-config"`). Never reference `env()`
outside config files. Confirm the exact env key names against the installed
package (next step's grep) — they have changed across Paddle Classic vs
Paddle Billing.

### 6. Wire the built-in webhook

Verify the installed package's webhook route and controller before relying on
them (mirror the project's "inspect vendor before coding" practice):

```
grep -rn "paddle/webhook\|class WebhookController\|cashier.webhook" vendor/laravel/cashier-paddle/src vendor/laravel/cashier-paddle/routes 2>/dev/null
```

Use Boost `search-docs` (queries like `["cashier paddle webhook", "webhook csrf"]`)
to confirm current guidance for Laravel 12's streamlined skeleton.

Cashier Paddle registers `POST paddle/webhook` (controller
`Laravel\Paddle\Http\Controllers\WebhookController`, route name
`cashier.webhook`) on the **web** group — it is **not** under the `api/v1`
prefix and **is** subject to web CSRF; it is unauthenticated by design
(Paddle signs requests with `PADDLE_WEBHOOK_SECRET`).

In `bootstrap/app.php`, inside the existing
`->withMiddleware(function (Middleware $middleware) { ... })` closure (which
already calls `$middleware->statefulApi()`), add a CSRF exception so Paddle
can POST:

```php
$middleware->validateCsrfTokens(except: ['paddle/*']);
```

Do not add `auth:sanctum` to this route — webhook auth is signature-based via
Cashier's webhook-signature middleware. Confirm `PADDLE_WEBHOOK_SECRET` gates it.

### 7. Add the billing feature flag

In `config/boilerplate.php`, add a `billing` section mirroring the existing
flag style (see `push`, `files`):

```php
'billing' => [
    'enabled' => (bool) env('BILLING_ENABLED', true),
    'provider' => 'paddle',
    'subscription_name' => env('BILLING_SUBSCRIPTION_NAME', 'default'),
],
```

Add `BILLING_ENABLED=` / `BILLING_SUBSCRIPTION_NAME=` to `.env.example`.
Reference it as `config('boilerplate.billing.*')` in the controllers below.

### 8. Scaffold the subscription API (match existing conventions exactly)

Generate files via `php artisan make:` (`--no-interaction`), then edit. Study
the named sibling files first and copy their structure precisely.

- **Read controller — `App\Http\Controllers\Api\Me\SubscriptionController`**
  (extends `App\Http\Controllers\Api\Me\Controller`). One
  `index(Request $request)` returning the authenticated user's subscriptions.
  Copy `app/Http/Controllers/Api/Me/DeviceController.php` (use `currentUser()`,
  `respondPaginated`/`respondOk`,
  `->through(fn ($s) => new SubscriptionResource($s))`).

- **Action controller — `App\Http\Controllers\Api\SubscriptionController`**
  (extends base `App\Http\Controllers\Controller`). Mirror
  `app/Http/Controllers/Api/DeviceController.php` for response style:
  - `store(StoreCheckoutRequest $request)`: Paddle is checkout-driven —
    create a checkout for the requested price
    (`$request->user()->checkout($request->validated('price'))` or
    `->subscribe(...)` per the installed v2 API; **verify the exact method
    against vendor source / `search-docs` before writing**) and return the
    checkout/transaction payload the client overlay needs via
    `respondCreated(...)`. Do not assume a subscription exists yet — it is
    created by Paddle post-checkout and confirmed by webhook.
  - `destroy(Request $request)`: cancel the active subscription
    (`->subscription(...)->cancel()`), guard when none exists with the base
    not-found responder, return `respondNoContent()` or the canceled resource.

- **Form Request — `App\Http\Requests\Subscriptions\StoreCheckoutRequest`**.
  Copy `app/Http/Requests/Devices/StoreDeviceRequest.php`: `authorize(): true`,
  **array-style** `rules()`, a `messages()` method. Rules:
  `'price' => ['required', 'string']`,
  `'quantity' => ['nullable', 'integer', 'min:1']`.

- **Resource — `App\Http\Resources\SubscriptionResource`**. Copy
  `app/Http/Resources/DeviceResource.php` (`@mixin`, null-safe
  `?->toIso8601String()`, omit sensitive raw ids). Expose at minimum:
  `type`/`name`, `status`, `quantity`, `on_trial` (bool), `trial_ends_at`,
  `ends_at`, `canceled` (bool). Do not leak Paddle customer/subscription
  external ids.

- **Routes** in `routes/api.php`, inside the existing
  `Route::middleware('auth:sanctum')` area, following the established
  `prefix(...)->name(...)` + dot-name convention:
  - `GET    /me/subscriptions` → `Me\SubscriptionController@index`,
    name `me.subscriptions.index` (place with the other `me.*` routes).
  - `POST   /subscriptions/checkout` → `SubscriptionController@store`,
    name `subscriptions.checkout`.
  - `DELETE /subscriptions` → `SubscriptionController@destroy`,
    name `subscriptions.destroy`.

### 9. Tests

Add PHPUnit feature tests under `tests/Feature/Billing/` (`Tests\Feature\Billing`
namespace, `RefreshDatabase`, `$this->actingAs($user)->json(...)`), copying
`tests/Feature/Me/ListMyDevicesTest.php`. **Do not hit the live Paddle API.**
Cover:

- Unauthenticated `GET /api/v1/me/subscriptions` → 401.
- `GET /api/v1/me/subscriptions` returns only the authenticated user's
  subscriptions (seed `subscriptions`/`customers` rows directly; no Paddle
  call for the read path).
- The Paddle webhook route exists, is `POST paddle/webhook`, is **not** behind
  `auth:sanctum`, and is CSRF-exempt — assert `$this->postJson('/paddle/webhook', [])`
  does not return 419/401 (Cashier rejects the unsigned body, the expected
  non-CSRF failure), or assert via the route collection.
- `SubscriptionResource` serializes the documented fields and omits external
  ids.

Add a `Database\Factories\SubscriptionFactory` only if a test needs to persist
rows; follow `database/factories/UserDeviceFactory.php` conventions and key it
through the customer/`User::factory()` relationship.

### 10. Finalize

```
vendor/bin/pint --dirty
php artisan test tests/Feature/Billing
```

All billing tests must pass before reporting done. Then ask the developer if
they want the full suite run.

## Notes

- **ULID fix is mandatory** — skipping step 3's edits (especially the
  `customers` billable key / `ulidMorphs`) is the most likely failure mode and
  is not covered by Cashier's own docs.
- Cashier's published migrations won't match this project's explicit
  `fk_/idx_/uq_` naming for *internal* columns; that deviation is acceptable.
  Only the billable key **type** must be ULID-correct.
- Paddle Billing has no server-side payment method — the `store` endpoint
  yields a checkout for the client; the subscription materializes via webhook.
  Do not scaffold a card-token flow.
- This skill is additive and provider-exclusive. To remove:
  `composer remove laravel/cashier-paddle`, roll back / delete the Cashier
  migrations, drop the `Billable` trait + import from `User`, delete the
  scaffolded controllers/request/resource/tests and their routes, remove the
  `billing` block from `config/boilerplate.php`, the CSRF `except` entry, and
  the Paddle env keys. Nothing else references it.
