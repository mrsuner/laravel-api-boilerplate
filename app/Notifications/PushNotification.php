<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * Provider-agnostic push notification.
 *
 * Ships with the FCM channel wired. Delivery fans out to every FCM token
 * the notifiable exposes via routeNotificationForFcm(). When the push
 * module is disabled no channel is selected, so this is a safe no-op on a
 * boilerplate that has not configured Firebase yet.
 *
 * The add-expo-push skill extends this class with an Expo channel and
 * toExpo() without touching the device registry or API.
 */
class PushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $data  Arbitrary key/value payload delivered alongside the alert (deep links, ids).
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! config('boilerplate.push.enabled', true)) {
            return [];
        }

        return ['fcm'];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(notification: new FcmNotification(
            title: $this->title,
            body: $this->body,
        )))->data($this->data);
    }
}
