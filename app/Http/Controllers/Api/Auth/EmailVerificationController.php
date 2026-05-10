<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * @group Email Verification
 *
 * Verify and resend email verification links.
 */
class EmailVerificationController extends Controller
{
    /**
     * Verify Email
     *
     * Confirms ownership of the email address via a temporary signed URL.
     * On success, redirects to the configured frontend URL when set,
     * otherwise returns a JSON success response.
     *
     * @unauthenticated
     *
     * @urlParam id integer required The user id encoded into the link.
     * @urlParam hash string required The sha1(email) hash encoded into the link.
     *
     * @response 200 {
     *   "message": "Email verified successfully."
     * }
     * @response 404 scenario="Invalid link" {
     *   "message": "Verification link is invalid."
     * }
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse|RedirectResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->respondToVerification(success: false, reason: 'invalid');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $this->respondToVerification(success: true);
    }

    /**
     * Resend Verification Email
     *
     * Re-sends the verification email to the authenticated user. Returns 200
     * even when the account is already verified (to avoid leaking state).
     *
     * @authenticated
     *
     * @response 200 {
     *   "message": "Verification email sent."
     * }
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->respondOk(message: 'Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return $this->respondOk(message: 'Verification email sent.');
    }

    private function respondToVerification(bool $success, ?string $reason = null): JsonResponse|RedirectResponse
    {
        $redirect = config('boilerplate.auth.email_verification.redirect_url');

        if ($redirect) {
            $query = ['status' => $success ? 'success' : 'failure'];

            if ($reason !== null) {
                $query['reason'] = $reason;
            }

            return redirect()->away($redirect.'?'.http_build_query($query));
        }

        if ($success) {
            return $this->respondOk(message: 'Email verified successfully.');
        }

        return $this->respondNotFound('Verification link is invalid.');
    }
}
