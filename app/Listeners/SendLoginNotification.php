<?php

namespace App\Listeners;

use App\Mail\LoginNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendLoginNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! config('boilerplate.auth.notifications.login_notification_enabled', true)) {
            return;
        }

        Mail::to($event->user)->send(new LoginNotification($event->user));
    }
}
