<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OtpRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\LoginOtp;
use App\Models\User;
use App\Services\Otp\Contracts\OtpService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @group Web Authentication
 *
 * Cookie-based authentication APIs for Single Page Applications (SPA)
 */
class WebAuthController extends Controller
{
    /**
     * Register
     *
     * Create a new user account and establish a session.
     *
     * @unauthenticated
     *
     * @bodyParam name string required The user's full name. Example: John Doe
     * @bodyParam email string required The user's email address. Must be unique. Example: john@example.com
     * @bodyParam password string required The password. Must be at least 8 characters. Example: secretpassword
     * @bodyParam password_confirmation string required Password confirmation. Must match password. Example: secretpassword
     *
     * @response 200 {
     *   "user": {"id": 1, "name": "John Doe", "email": "john@example.com"},
     *   "message": "Registered successfully."
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"email": ["This email is already registered."]}
     * }
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'is_active' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return $this->respondOk(
            data: ['user' => $user],
            message: 'Registered successfully.'
        );
    }

    /**
     * Login
     *
     * Authenticate user with email and password, establish a session.
     *
     * @unauthenticated
     *
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password. Example: secretpassword
     *
     * @response 200 {
     *   "user": {"id": 1, "name": "John Doe", "email": "john@example.com"},
     *   "message": "Logged in successfully."
     * }
     * @response 403 scenario="Account inactive" {
     *   "message": "Account is inactive."
     * }
     * @response 422 scenario="Invalid credentials" {
     *   "message": "The given data was invalid.",
     *   "errors": {"email": ["The provided credentials are incorrect."]}
     * }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        $user->update(['last_login_at' => now()]);

        event(new Login('web', $user, false));

        Auth::login($user);

        return $this->respondOk(
            data: ['user' => $user],
            message: 'Logged in successfully.'
        );
    }

    /**
     * Request OTP
     *
     * Send a One-Time Password to the user's email for passwordless authentication.
     *
     * @unauthenticated
     *
     * @bodyParam email string required The email address to send the OTP to. Example: john@example.com
     *
     * @response 200 {
     *   "message": "OTP sent to your email."
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"email": ["The email field must be a valid email address."]}
     * }
     */
    public function requestOtp(OtpRequest $request, OtpService $otpService): JsonResponse
    {
        $email = $request->input('email');
        $token = $otpService->create($email);

        Mail::to($email)->send(new LoginOtp($token));

        return $this->respondOk(message: 'OTP sent to your email.');
    }

    /**
     * Verify OTP
     *
     * Verify the One-Time Password and establish a session.
     * Creates a new account if the email is not registered.
     *
     * @unauthenticated
     *
     * @bodyParam email string required The email address. Example: john@example.com
     * @bodyParam token string required The 6-digit OTP code. Example: 123456
     *
     * @response 200 {
     *   "user": {"id": 1, "name": "john", "email": "john@example.com"},
     *   "message": "Logged in successfully."
     * }
     * @response 403 scenario="Account inactive" {
     *   "message": "Account is inactive."
     * }
     * @response 422 scenario="Invalid OTP" {
     *   "message": "The given data was invalid.",
     *   "errors": {"token": ["Invalid or expired OTP."]}
     * }
     */
    public function verifyOtp(OtpVerifyRequest $request, OtpService $otpService): JsonResponse
    {
        $email = $request->input('email');
        $otpToken = $request->input('token');

        if (! $otpService->verify($email, $otpToken)) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired OTP.'],
            ]);
        }

        $isNewUser = ! User::where('email', $email)->exists();

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => explode('@', $email)[0],
                'password' => Hash::make(Str::random(32)),
                'is_active' => true,
            ]
        );

        if ($isNewUser) {
            event(new Registered($user));
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        $user->update(['last_login_at' => now()]);

        event(new Login('web', $user, false));

        Auth::login($user);

        return $this->respondOk(
            data: ['user' => $user],
            message: 'Logged in successfully.'
        );
    }

    /**
     * Forgot Password
     *
     * Send a password reset link to the user's email.
     *
     * @unauthenticated
     *
     * @bodyParam email string required The registered email address. Example: john@example.com
     *
     * @response 200 {
     *   "message": "Password reset link sent to your email."
     * }
     * @response 422 scenario="Email not found" {
     *   "message": "The given data was invalid.",
     *   "errors": {"email": ["We could not find an account with that email."]}
     * }
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->respondOk(message: 'Password reset link sent to your email.');
    }

    /**
     * Reset Password
     *
     * Reset the user's password using the reset token.
     *
     * @unauthenticated
     *
     * @bodyParam token string required The password reset token from email. Example: abc123def456...
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The new password. Must be at least 8 characters. Example: newsecretpassword
     * @bodyParam password_confirmation string required Password confirmation. Must match password. Example: newsecretpassword
     *
     * @response 200 {
     *   "message": "Password has been reset successfully."
     * }
     * @response 422 scenario="Invalid token" {
     *   "message": "The given data was invalid.",
     *   "errors": {"email": ["This password reset token is invalid."]}
     * }
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordResetEvent($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->respondOk(message: 'Password has been reset successfully.');
    }

    /**
     * Logout
     *
     * Destroy the current session.
     *
     * @authenticated
     *
     * @response 200 {
     *   "message": "Logged out successfully."
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        event(new Logout('web', $user));

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->respondOk(message: 'Logged out successfully.');
    }

    /**
     * Change Password
     *
     * Change the authenticated user's password.
     *
     * @authenticated
     *
     * @bodyParam current_password string required The current password. Example: oldsecretpassword
     * @bodyParam password string required The new password. Must be at least 8 characters. Example: newsecretpassword
     * @bodyParam password_confirmation string required New password confirmation. Must match password. Example: newsecretpassword
     *
     * @response 200 {
     *   "message": "Password changed successfully."
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 422 scenario="Wrong current password" {
     *   "message": "The given data was invalid.",
     *   "errors": {"current_password": ["The current password is incorrect."]}
     * }
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return $this->respondOk(message: 'Password changed successfully.');
    }
}
