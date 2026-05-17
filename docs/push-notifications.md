# Push Notifications

A provider-agnostic device-token registry with **FCM wired out of the box**. Clients register their push token; the backend stores it per device and fans notifications out to every token a user owns. The schema and API are provider-neutral — only the *sending* side is provider-specific.

## How It Works

- A client obtains a push token from its provider SDK (Firebase, Expo, APNs) and `POST`s it to `/devices` on every app launch.
- Registration is **idempotent and keyed by the globally unique `push_token`**, not by user. Re-sending an existing token — even from a different account — transfers that device row to the new owner. Push tokens are recycled by the OS across reinstalls and account switches, so the token is the device identity; this guarantees the previous user never receives notifications meant for whoever holds the device now.
- Sending a notification routes through Laravel's notification system: `App\Notifications\PushNotification` selects the FCM channel and multicasts to every FCM token returned by `User::routeNotificationForFcm()`.
- The whole module is config-gated. With `boilerplate.push.enabled = false` the device endpoints `404` and `PushNotification` selects no channel, so the app stays bootable with no Firebase project configured.

## Defaults

| Setting | Default | Meaning |
|---|---|---|
| `push.enabled` | `true` | Master switch. When false, `/devices*` 404 and notifications no-op. |
| `push.default_provider` | `fcm` | Informational; each device row records its own provider. |
| `FIREBASE_CREDENTIALS` | _(empty)_ | Absolute path to the Firebase service-account JSON. Only needed when actually dispatching FCM. |

## Configuration

`config/boilerplate.php → push`, with matching env vars in `.env.example`:

```env
PUSH_ENABLED=true
PUSH_DEFAULT_PROVIDER=fcm
# Read by kreait/laravel-firebase. Required only when you dispatch FCM.
FIREBASE_CREDENTIALS=/abs/path/to/firebase-service-account.json
```

FCM credentials are resolved by `kreait/laravel-firebase` (pulled in by `laravel-notification-channels/fcm`) from the `FIREBASE_CREDENTIALS` env var.

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/v1/devices` | Register / refresh the calling user's device. Idempotent. |
| `DELETE` | `/api/v1/devices/{device}` | Unregister a device. Owner-only. |
| `GET` | `/api/v1/me/devices` | List the current user's devices (read-only `Me\*` layer). |

All three require Sanctum auth. The raw `push_token` is a sending credential and is **never** returned by any endpoint.

### Register (`POST /devices`)

```json
{
  "platform": "ios",          // ios | android | web
  "provider": "fcm",          // fcm | expo | apns
  "push_token": "fGc1...token",
  "device_id": "7B3F2A10-...", // optional, stable client identifier
  "device_name": "John's iPhone", // optional
  "app_version": "1.4.2"          // optional
}
```

```json
// 201 Created
{
  "data": {
    "id": "01HX...",
    "platform": "ios",
    "provider": "fcm",
    "device_id": "7B3F2A10-...",
    "device_name": "John's iPhone",
    "app_version": "1.4.2",
    "last_used_at": "2026-05-17T10:00:00+00:00",
    "created_at": "2026-05-17T10:00:00+00:00"
  }
}
```

### Listing My Devices (`GET /me/devices`)

Returns the standard paginated envelope. Query params: `provider` (filter), `sort` (`last_used_at` / `-last_used_at` / `created_at` / `-created_at`, default `-last_used_at`), `per_page` (1–100, default 15), `page`.

## Sending a Notification

`App\Notifications\PushNotification` is a ready example. It is `ShouldQueue`, so delivery happens on the queue.

```php
use App\Notifications\PushNotification;

$user->notify(new PushNotification(
    title: 'Order shipped',
    body: 'Your order #1234 is on its way.',
    data: ['order_id' => '1234', 'url' => '/orders/1234'],
));
```

`via()` returns `['fcm']` only when `boilerplate.push.enabled` is true; `routeNotificationForFcm()` supplies every FCM token the user has. Build your own notifications the same way — implement `toFcm()` and let the channel multicast.

## Expo (Opt-In)

Expo is intentionally **not** installed by default to keep the base lean (most projects use only FCM). The table, API, and `App\Enums\PushProvider` are already provider-agnostic — `PushProvider::Expo` exists and clients can register Expo devices today; only the sending side is absent.

To wire Expo delivery in minutes, run the **`add-expo-push`** Claude skill (`.claude/skills/add-expo-push/SKILL.md`). It installs `laravel-notification-channels/expo`, adds `User::routeNotificationForExpo()`, extends `PushNotification` with `toExpo()` and an Expo `via()` branch, and adds tests — **without touching the schema, controllers, requests, resource, or routes**. The skill also documents the clean removal path.

## Schema

`user_devices`:

| Column | Type | Notes |
|---|---|---|
| `id` | ULID | Primary key. |
| `user_id` | ULID FK → `users` | `fk_user_devices__user_id__users`, `cascadeOnDelete`. |
| `platform` | string(20) | `ios` / `android` / `web` (cast to `DevicePlatform`). |
| `provider` | string(20) | `fcm` / `expo` / `apns` (cast to `PushProvider`). |
| `push_token` | string | `uq_user_devices__push_token` — globally unique → ownership transfer. |
| `device_id` | string, nullable | Client-supplied stable device identifier. |
| `device_name` | string, nullable | Human-readable label. |
| `app_version` | string(50), nullable | Client app version. |
| `last_used_at` | timestamp, nullable | Refreshed on every registration. |

Composite index `idx_user_devices__user_id_provider`. The model uses the `Auditable` trait with `push_token` excluded from audit payloads.

## Customizing

| Want | Do |
|---|---|
| A different notification payload | Write your own `Notification` with `toFcm()`; copy `PushNotification` as a template. |
| Restrict who can register | Add Laratrust `permission` middleware to the `/devices` route group (see [rbac.md](rbac.md)). |
| Prune stale devices | Add a scheduled command deleting rows with old `last_used_at` (mirror `audit:prune`). |
| Add Expo / APNs delivery | Run the `add-expo-push` skill (Expo); APNs follows the same channel pattern. |

## Key Files

| File | Purpose |
|---|---|
| `config/boilerplate.php → push` | Defaults and toggles. |
| `app/Models/UserDevice.php` | Model with `registerForUser()` auto-transfer, `scopeOwnedBy`/`scopeForProvider`. |
| `app/Enums/DevicePlatform.php`, `app/Enums/PushProvider.php` | Provider-agnostic value sets. |
| `app/Models/User.php` | `devices()` relation + `routeNotificationForFcm()`. |
| `app/Http/Controllers/Api/DeviceController.php` | Register / unregister. |
| `app/Http/Controllers/Api/Me/DeviceController.php` | `GET /me/devices` listing. |
| `app/Http/Requests/Devices/StoreDeviceRequest.php` | Registration validation. |
| `app/Http/Requests/Me/ListDevicesRequest.php` | `/me/devices` query validation. |
| `app/Http/Resources/DeviceResource.php` | API envelope (never exposes `push_token`). |
| `app/Notifications/PushNotification.php` | Example FCM notification. |
| `database/migrations/*_create_user_devices_table.php` | Schema. |
| `.claude/skills/add-expo-push/SKILL.md` | One-shot Expo integration skill. |
| `tests/Feature/Devices/*`, `tests/Feature/Me/ListMyDevicesTest.php` | Behavior source of truth. |
