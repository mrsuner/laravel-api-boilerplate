<?php

namespace App\Listeners;

use App\Mail\PasswordResetConfirmation;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetConfirmation implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(PasswordReset $event): void
    {
        if (! config('boilerplate.auth.notifications.password_reset_confirmation_enabled', true)) {
            return;
        }

        Mail::to($event->user)->send(new PasswordResetConfirmation($event->user));
    }
}
