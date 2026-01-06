<?php

namespace App\Listeners;

use App\Mail\WelcomeEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        if (! config('boilerplate.auth.notifications.welcome_email_enabled', true)) {
            return;
        }

        Mail::to($event->user)->send(new WelcomeEmail($event->user));
    }
}
