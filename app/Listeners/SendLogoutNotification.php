<?php

namespace App\Listeners;

use App\Mail\LogoutNotification;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendLogoutNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        if (! config('boilerplate.auth.notifications.logout_notification_enabled', false)) {
            return;
        }

        if ($event->user) {
            Mail::to($event->user)->send(new LogoutNotification($event->user));
        }
    }
}
