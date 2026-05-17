<?php

namespace App\Enums;

/**
 * Push transport a device token belongs to.
 *
 * The table and API are provider-agnostic; only the sending side is
 * provider-specific. FCM ships wired out of the box — Expo/APNs values
 * exist so the schema never needs to change when those are added (see the
 * add-expo-push skill for the Expo sending integration).
 */
enum PushProvider: string
{
    case Fcm = 'fcm';
    case Expo = 'expo';
    case Apns = 'apns';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
