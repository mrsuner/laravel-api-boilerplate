<?php

namespace Tests\Feature\Devices;

use App\Enums\PushProvider;
use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\PushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_notification_for_fcm_returns_only_fcm_tokens(): void
    {
        $user = User::factory()->create();
        UserDevice::factory()->for($user)->fcm()->create(['push_token' => 'fcm-1']);
        UserDevice::factory()->for($user)->fcm()->create(['push_token' => 'fcm-2']);
        UserDevice::factory()->for($user)->expo()->create();

        $tokens = $user->routeNotificationForFcm(new PushNotification('t', 'b'));

        sort($tokens);
        $this->assertSame(['fcm-1', 'fcm-2'], $tokens);
    }

    public function test_notification_is_routed_to_fcm_channel(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        UserDevice::factory()->for($user)->fcm()->create();

        $user->notify(new PushNotification('Hello', 'World', ['url' => '/home']));

        Notification::assertSentTo(
            $user,
            PushNotification::class,
            fn (PushNotification $n, array $channels): bool => $channels === ['fcm']
        );
    }

    public function test_no_channel_is_selected_when_push_module_disabled(): void
    {
        config(['boilerplate.push.enabled' => false]);
        Notification::fake();

        $user = User::factory()->create();
        UserDevice::factory()->for($user)->fcm()->create();

        $user->notify(new PushNotification('Hello', 'World'));

        // via() returns no channels, so the notification is never dispatched.
        Notification::assertNotSentTo($user, PushNotification::class);
    }

    public function test_to_fcm_builds_message_with_notification_and_data(): void
    {
        $user = User::factory()->create();

        $message = (new PushNotification('Title', 'Body', ['k' => 'v']))->toFcm($user);

        $this->assertSame('Title', $message->notification->title);
        $this->assertSame('Body', $message->notification->body);
        $this->assertSame(['k' => 'v'], $message->data);
        $this->assertSame(PushProvider::Fcm->value, 'fcm');
    }
}
