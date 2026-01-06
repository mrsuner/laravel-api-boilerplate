<?php

namespace Tests\Feature\Listeners;

use App\Listeners\SendLoginNotification;
use App\Listeners\SendLogoutNotification;
use App\Listeners\SendPasswordResetConfirmation;
use App\Listeners\SendWelcomeEmail;
use App\Mail\LoginNotification;
use App\Mail\LogoutNotification;
use App\Mail\PasswordResetConfirmation;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthListenersTest extends TestCase
{
    use RefreshDatabase;

    // === SendWelcomeEmail Listener Tests ===

    public function test_welcome_email_sent_on_registration(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $listener = new SendWelcomeEmail;
        $listener->handle(new Registered($user));

        Mail::assertSent(WelcomeEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_welcome_email_not_sent_when_disabled(): void
    {
        Mail::fake();
        config(['boilerplate.auth.notifications.welcome_email_enabled' => false]);

        $user = User::factory()->create();

        $listener = new SendWelcomeEmail;
        $listener->handle(new Registered($user));

        Mail::assertNotSent(WelcomeEmail::class);
    }

    public function test_register_endpoint_dispatches_registered_event(): void
    {
        Event::fake([Registered::class]);

        $this->postJson('/api/v1/auth/app/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Event::assertDispatched(Registered::class);
    }

    // === SendLoginNotification Listener Tests ===

    public function test_login_notification_sent_on_login(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $listener = new SendLoginNotification;
        $listener->handle(new Login('sanctum', $user, false));

        Mail::assertSent(LoginNotification::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_login_notification_not_sent_when_disabled(): void
    {
        Mail::fake();
        config(['boilerplate.auth.notifications.login_notification_enabled' => false]);

        $user = User::factory()->create();

        $listener = new SendLoginNotification;
        $listener->handle(new Login('sanctum', $user, false));

        Mail::assertNotSent(LoginNotification::class);
    }

    public function test_login_endpoint_dispatches_login_event(): void
    {
        Event::fake([Login::class]);

        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/auth/app/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        Event::assertDispatched(Login::class);
    }

    // === SendLogoutNotification Listener Tests ===

    public function test_logout_notification_sent_when_enabled(): void
    {
        Mail::fake();
        config(['boilerplate.auth.notifications.logout_notification_enabled' => true]);

        $user = User::factory()->create();

        $listener = new SendLogoutNotification;
        $listener->handle(new Logout('sanctum', $user));

        Mail::assertSent(LogoutNotification::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_logout_notification_not_sent_when_disabled(): void
    {
        Mail::fake();
        config(['boilerplate.auth.notifications.logout_notification_enabled' => false]);

        $user = User::factory()->create();

        $listener = new SendLogoutNotification;
        $listener->handle(new Logout('sanctum', $user));

        Mail::assertNotSent(LogoutNotification::class);
    }

    public function test_logout_endpoint_dispatches_logout_event(): void
    {
        Event::fake([Logout::class]);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/app/logout');

        Event::assertDispatched(Logout::class);
    }

    // === SendPasswordResetConfirmation Listener Tests ===

    public function test_password_reset_confirmation_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $listener = new SendPasswordResetConfirmation;
        $listener->handle(new PasswordReset($user));

        Mail::assertSent(PasswordResetConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_password_reset_confirmation_not_sent_when_disabled(): void
    {
        Mail::fake();
        config(['boilerplate.auth.notifications.password_reset_confirmation_enabled' => false]);

        $user = User::factory()->create();

        $listener = new SendPasswordResetConfirmation;
        $listener->handle(new PasswordReset($user));

        Mail::assertNotSent(PasswordResetConfirmation::class);
    }

    // === Integration Tests ===

    public function test_otp_verify_dispatches_registered_event_for_new_user(): void
    {
        Event::fake([Registered::class, Login::class]);
        Mail::fake();

        // Create OTP for a new email
        $this->postJson('/api/v1/auth/app/otp', [
            'email' => 'newuser@example.com',
        ]);

        // Get the OTP from cache/database
        $otpService = app(\App\Services\Otp\Contracts\OtpService::class);
        $token = $otpService->create('newuser@example.com');

        $this->postJson('/api/v1/auth/app/otp/verify', [
            'email' => 'newuser@example.com',
            'token' => $token,
            'device_name' => 'Test Device',
        ]);

        Event::assertDispatched(Registered::class);
        Event::assertDispatched(Login::class);
    }

    public function test_otp_verify_does_not_dispatch_registered_for_existing_user(): void
    {
        Event::fake([Registered::class, Login::class]);
        Mail::fake();

        $user = User::factory()->create(['email' => 'existing@example.com']);

        $otpService = app(\App\Services\Otp\Contracts\OtpService::class);
        $token = $otpService->create('existing@example.com');

        $this->postJson('/api/v1/auth/app/otp/verify', [
            'email' => 'existing@example.com',
            'token' => $token,
            'device_name' => 'Test Device',
        ]);

        Event::assertNotDispatched(Registered::class);
        Event::assertDispatched(Login::class);
    }

    // === Listener Discovery Tests ===

    public function test_listeners_are_discovered_by_laravel(): void
    {
        $events = app('events');

        // Check that listeners are registered for events
        $this->assertNotEmpty(
            $events->getListeners(Registered::class),
            'No listeners found for Registered event'
        );

        $this->assertNotEmpty(
            $events->getListeners(Login::class),
            'No listeners found for Login event'
        );

        $this->assertNotEmpty(
            $events->getListeners(Logout::class),
            'No listeners found for Logout event'
        );

        $this->assertNotEmpty(
            $events->getListeners(PasswordReset::class),
            'No listeners found for PasswordReset event'
        );
    }
}
