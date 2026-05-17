---
name: add-expo-push
description: Wire Expo push delivery into this boilerplate's existing device registry. Use when the developer wants Expo (React Native / EAS) notifications in addition to the out-of-the-box FCM channel. Installs the Expo notification channel and extends App\Notifications\PushNotification and the User model — the user_devices table, /devices and /me/devices API, and PushProvider enum already support Expo and do NOT change.
---

# Add Expo Push Delivery

This boilerplate ships FCM wired out of the box. The device registry
(`user_devices`), the `/devices` + `/me/devices` API, and the
`App\Enums\PushProvider` enum are already provider-agnostic — `PushProvider::Expo`
exists and clients can register Expo devices today. **Only the sending side is
missing.** This skill adds it without touching the schema or API.

## Preconditions

- Confirm the package is not already installed:
  `grep laravel-notification-channels/expo composer.json`. If present, stop and
  tell the developer Expo is already wired.
- `App\Notifications\PushNotification` and `App\Models\User::routeNotificationForFcm()`
  must still exist (they are the FCM baseline this extends). If they were
  removed, ask the developer how they want Expo structured instead.

## Steps

### 1. Install the channel

```
composer require laravel-notification-channels/expo "^2.2" --no-interaction
```

Optionally set `EXPO_ACCESS_TOKEN` in `.env` / `.env.example` (only needed if
the developer enabled "Enhanced Security for Push Notifications" in their Expo
account — leave blank otherwise).

### 2. Verify the installed API before writing code

The v2 API is strict about token types. Inspect the installed source so the
generated code matches exactly:

```
ls vendor/laravel-notification-channels/expo/src
grep -rn "function create\|function to\|class ExpoMessage\|class ExpoPushToken" vendor/laravel-notification-channels/expo/src
```

Expected v2 shape (confirm against the grep above):
- Channel: `NotificationChannels\Expo\ExpoChannel`
- Message: `NotificationChannels\Expo\ExpoMessage::create()->title()->body()->data()`
- Token: `NotificationChannels\Expo\ExpoPushToken::make($rawToken)` — `routeNotificationForExpo()` must return `ExpoPushToken` instances, not raw strings. Skip tokens that fail `ExpoPushToken::isValid()` so a malformed row cannot abort the whole multicast.

### 3. Add Expo routing to the User model

In `app/Models/User.php`, mirror `routeNotificationForFcm()`. Return
`ExpoPushToken` instances for devices whose `provider` is `PushProvider::Expo`,
filtering out any token Expo rejects as invalid. Match the existing method's
docblock style and the `forProvider()` scope already on `UserDevice`.

### 4. Extend PushNotification

In `app/Notifications\PushNotification`:

- Update `via()` so that, when push is enabled, it returns the channels the
  notifiable can actually receive — keep `'fcm'` and add Expo's channel
  (`ExpoChannel::class`) only when `$notifiable->routeNotificationForExpo($this)`
  is non-empty. This avoids dispatching an empty Expo request to users with no
  Expo devices. Keep the `config('boilerplate.push.enabled')` short-circuit.
- Add `toExpo(object $notifiable): ExpoMessage` building title/body/data from
  the constructor properties, parallel to `toFcm()`.

Keep the class `ShouldQueue` and the existing constructor signature unchanged so
existing callers and tests keep working.

### 5. Tests

Add `tests/Feature/Devices/ExpoPushNotificationTest.php` (PHPUnit,
`RefreshDatabase`), covering:

- `routeNotificationForExpo()` returns only `PushProvider::Expo` device tokens
  and excludes FCM ones.
- With `Notification::fake()`, a user that has an Expo device gets the Expo
  channel selected; a user with only FCM devices does not.
- `toExpo()` builds a message carrying the title, body, and data.

Use `UserDevice::factory()->for($user)->expo()` — the factory state already
exists. Follow the conventions in the sibling `PushNotificationTest.php`.

### 6. Finalize

```
vendor/bin/pint --dirty
php artisan test tests/Feature/Devices
```

All device tests (FCM baseline + new Expo) must pass before reporting done.

## Notes

- Do **not** create a migration or modify `user_devices`, the controllers,
  requests, resource, or routes — they are already Expo-ready.
- Do not remove or weaken the FCM path; Expo is additive.
- To later remove Expo: `composer remove laravel-notification-channels/expo`,
  then revert the `routeNotificationForExpo()` method, the `toExpo()` method,
  the `via()` Expo branch, and delete the Expo test. Nothing else references it.
