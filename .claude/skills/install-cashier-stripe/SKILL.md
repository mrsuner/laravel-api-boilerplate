---
name: install-cashier-stripe
description: Scaffold Laravel Cashier (Stripe) into this API boilerplate following its existing conventions. Use when the developer wants Stripe subscription billing. Installs laravel/cashier, ULID-fixes the published migrations, adds the Billable trait, wires the built-in Stripe webhook (CSRF/auth-exempt), and scaffolds a versioned Sanctum-protected subscription API (controllers, Form Requests, Resource, routes) plus PHPUnit tests. Mutually exclusive with install-cashier-paddle.
---

# Install Laravel Cashier (Stripe)

This boilerplate ships **no billing scaffold**. This skill installs Cashier
Stripe and wires a subscription API that matches the project's existing
conventions (versioned `api/v1` routes, `Me\*` read controllers, base
`Controller` responders, array-rule Form Requests, `JsonResource`s, ULID
models, PHPUnit + `RefreshDatabase`). Webhooks use **Cashier's built-in
controller** — we do not hand-roll event handling.

## Preconditions

- Confirm Cashier is not already installed:
  `grep '"laravel/cashier"' composer.json`. If present, stop and tell the
  developer Stripe billing is already wired.
- **Mutually exclusive with Paddle.** Run
  `grep '"laravel/cashier-paddle"' composer.json`. If present, stop — a project
  should bill through one provider. Ask the developer to remove Paddle first or
  reconsider.
- `app/Models/User.php` must still use `HasUlids` (the ULID-fix step below
  depends on it). If User was changed to integer keys, ask the developer how
  they want the billing tables keyed before proceeding.

## Steps

### 1. Confirm scope with the developer

Ask which subscription type name to use (Cashier's default is `default`) and
whether they collect the payment method client-side via Stripe.js (the API
expects a `payment_method` token — there is no server-side card collection).
Do not invent Stripe price IDs; the API takes the `price` in the request body.

### 2. Install the package

```
composer require laravel/cashier --no-interaction
```

Laravel 12 resolves Cashier Stripe v15+. Do not pin unless composer fails;
if it does, retry with `"laravel/cashier:^15.0"`.

### 3. Publish migrations and ULID-fix them (CRITICAL — boilerplate-specific)

```
php artisan vendor:publish --tag="cashier-migrations" --no-interaction
```

Cashier's published migrations assume **bigint** user keys. This project's
`users` table uses **ULID** (`HasUlids`). Before running `migrate`, open every
freshly published Cashier migration and fix the key types:

- The `subscriptions` (and `subscription_items` if present) table's
  `$table->foreignId('user_id')` / `unsignedBigInteger('user_id')` →
  `$table->foreignUlid('user_id')`. Match the project's other migrations:
  add `->constrained('users')->cascadeOnDelete()` and an explicit FK name
  (`fk_subscriptions__user_id__users`) — check
  `database/migrations/*_create_user_devices_table.php` for the exact naming
  pattern (`fk_…`, `idx_…`, `uq_…`).
- The `add_customer_columns` migration adds `stripe_id`, `pm_type`,
  `pm_last_four`, `trial_ends_at` to `users` — these are string/nullable and
  are fine as-is. Keep the `stripe_id` index.
- Do **not** otherwise rewrite Cashier's internal column set.

Then:

```
php artisan migrate --no-interaction
```

If `migrate` fails on a foreign-key/type error, the ULID fix above was
incomplete — re-check before continuing.

### 4. Add the Billable trait to User

In `app/Models/User.php`, add `use Laravel\Cashier\Billable;` and include
`Billable` in the class's `use` trait list, alongside the existing
`HasApiTokens, HasFactory, HasRolesAndPermissions, HasUlids, Notifiable`.
Match the existing import ordering and trait-list style. Do not change the
`casts()` method or interfaces.

### 5. Environment variables

Add to `.env` and `.env.example` (leave example values blank):

```
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

These are read by Cashier's own `config/cashier.php` (publish it only if the
developer needs to customize: `php artisan vendor:publish --tag="cashier-config"`).
Never reference `env()` outside config files.

### 6. Wire the built-in webhook

Verify the installed Cashier's webhook route and controller before relying on
them (mirror the project's "inspect vendor before coding" practice):

```
grep -rn "stripe/webhook\|class WebhookController\|cashier.webhook" vendor/laravel/cashier/src vendor/laravel/cashier/routes 2>/dev/null
```

Use Boost `search-docs` (queries like `["cashier webhook", "webhook csrf"]`)
to confirm current guidance for Laravel 12's streamlined skeleton.

Cashier registers `POST stripe/webhook` (controller
`Laravel\Cashier\Http\Controllers\WebhookController`, route name
`cashier.webhook`) on the **web** group — it is **not** under the `api/v1`
prefix and **is** subject to web CSRF and is unauthenticated by design
(Stripe signs requests with `STRIPE_WEBHOOK_SECRET`).

In `bootstrap/app.php`, inside the existing
`->withMiddleware(function (Middleware $middleware) { ... })` closure (which
already calls `$middleware->statefulApi()`), add a CSRF exception so Stripe
can POST:

```php
$middleware->validateCsrfTokens(except: ['stripe/*']);
```

Do not add `auth:sanctum` to this route — webhook auth is signature-based via
Cashier's `VerifyWebhookSignature` middleware. Confirm
`STRIPE_WEBHOOK_SECRET` gates it.

### 7. Add the billing feature flag

In `config/boilerplate.php`, add a `billing` section mirroring the existing
flag style (see `push`, `files`):

```php
'billing' => [
    'enabled' => (bool) env('BILLING_ENABLED', true),
    'provider' => 'stripe',
    'subscription_name' => env('BILLING_SUBSCRIPTION_NAME', 'default'),
],
```

Add `BILLING_ENABLED=` / `BILLING_SUBSCRIPTION_NAME=` to `.env.example`.
Reference it as `config('boilerplate.billing.*')` in the controllers below.

### 8. Scaffold the subscription API (match existing conventions exactly)

Generate files via `php artisan make:` (`--no-interaction`), then edit. Study
the named sibling files first and copy their structure precisely.

- **Read controller — `App\Http\Controllers\Api\Me\SubscriptionController`**
  (extends `App\Http\Controllers\Api\Me\Controller`). One `index(Request $request)`
  returning the authenticated user's subscriptions as a paginated/collection
  response. Copy the structure of
  `app/Http/Controllers/Api/Me/DeviceController.php` (use `currentUser()`,
  `respondPaginated` / `respondOk`, `->through(fn ($s) => new SubscriptionResource($s))`).

- **Action controller — `App\Http\Controllers\Api\SubscriptionController`**
  (extends base `App\Http\Controllers\Controller`). Mirror
  `app/Http/Controllers/Api/DeviceController.php` for response style
  (`respondCreated`, `respondNoContent`, explicit `JsonResponse` returns):
  - `store(StoreSubscriptionRequest $request)`:
    `$request->user()->newSubscription(config('boilerplate.billing.subscription_name'), $request->validated('price'))->create($request->validated('payment_method'))`,
    return `respondCreated(new SubscriptionResource(...))`. Catch
    `Laravel\Cashier\Exceptions\IncompletePayment` and respond with the
    project's `respondUnprocessable` (or appropriate responder), including the
    payment-intent client secret so the client can complete SCA.
  - `destroy(Request $request)`: cancel the active subscription
    (`->subscription(...)->cancel()`), guard when none exists with the base
    responder for not-found, return `respondNoContent()` or the canceled
    resource.

- **Form Request — `App\Http\Requests\Subscriptions\StoreSubscriptionRequest`**.
  Copy `app/Http/Requests/Devices/StoreDeviceRequest.php`: `authorize(): true`,
  **array-style** `rules()`, a `messages()` method. Rules:
  `'price' => ['required', 'string']`,
  `'payment_method' => ['required', 'string']`.

- **Resource — `App\Http\Resources\SubscriptionResource`**. Copy
  `app/Http/Resources/DeviceResource.php` (`@mixin`, null-safe
  `?->toIso8601String()`, omit sensitive raw ids). Expose at minimum:
  `type`/`name`, `stripe_status`, `quantity`, `on_trial` (bool),
  `trial_ends_at`, `ends_at`, `canceled` (bool). Do not leak `stripe_id`.

- **Routes** in `routes/api.php`. Add inside the existing
  `Route::middleware('auth:sanctum')` area, following the established
  `prefix(...)->name(...)` + dot-name convention:
  - `GET  /me/subscriptions` → `Me\SubscriptionController@index`,
    name `me.subscriptions.index` (place with the other `me.*` routes).
  - `POST   /subscriptions` → `SubscriptionController@store`,
    name `subscriptions.store`.
  - `DELETE /subscriptions` → `SubscriptionController@destroy`,
    name `subscriptions.destroy`.

### 9. Tests

Add PHPUnit feature tests under `tests/Feature/Billing/` (`Tests\Feature\Billing`
namespace, `RefreshDatabase`, `$this->actingAs($user)->json(...)`), copying the
style of `tests/Feature/Me/ListMyDevicesTest.php`. **Do not hit the live Stripe
API.** Cover:

- Unauthenticated `GET /api/v1/me/subscriptions` → 401.
- `GET /api/v1/me/subscriptions` returns only the authenticated user's
  subscriptions (seed `subscriptions` rows directly via the table/relation;
  no Stripe call needed for the read path).
- The Stripe webhook route exists, is `POST stripe/webhook`, is **not**
  behind `auth:sanctum`, and is CSRF-exempt — assert via
  `$this->postJson('/stripe/webhook', [])` not returning 419/401 (Cashier
  will reject the unsigned body, which is the expected non-CSRF failure), or
  assert the route exists through the route collection.
- `SubscriptionResource` serializes the documented fields and omits
  `stripe_id`.

Add a `Database\Factories\SubscriptionFactory` only if a test needs to
persist `subscriptions` rows; key it with `User::factory()` and follow
`database/factories/UserDeviceFactory.php` conventions.

### 10. Finalize

```
vendor/bin/pint --dirty
php artisan test tests/Feature/Billing
```

All billing tests must pass before reporting done. Then ask the developer if
they want the full suite run.

## Notes

- **ULID fix is mandatory** — skipping step 3's edits is the most likely
  failure mode and is not covered by Cashier's own docs.
- Cashier's published migrations won't match this project's explicit
  `fk_/idx_/uq_` naming for *internal* columns; that deviation is acceptable.
  Only the `user_id` key **type** must be ULID-correct.
- This skill is additive and provider-exclusive. To remove:
  `composer remove laravel/cashier`, roll back / delete the Cashier
  migrations, drop the `Billable` trait + import from `User`, delete the
  scaffolded controllers/request/resource/tests and their routes, remove the
  `billing` block from `config/boilerplate.php`, the CSRF `except` entry, and
  the Stripe env keys. Nothing else references it.
- For server-driven flows (no Stripe.js) advise the developer to use Stripe
  Checkout Sessions instead — out of scope here; this skeleton assumes a
  client-collected `payment_method`.
