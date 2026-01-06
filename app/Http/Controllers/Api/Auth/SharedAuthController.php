<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Shared Authentication
 *
 * Shared authentication APIs that work with both token and cookie-based authentication
 */
class SharedAuthController extends Controller
{
    /**
     * Get Current User
     *
     * Retrieve the authenticated user's information.
     * Works with both token-based and cookie-based authentication.
     *
     * @authenticated
     *
     * @response 200 {
     *   "id": 1,
     *   "name": "John Doe",
     *   "email": "john@example.com",
     *   "email_verified_at": "2024-01-01T00:00:00.000000Z",
     *   "is_active": true,
     *   "last_login_at": "2024-01-15T10:30:00.000000Z",
     *   "created_at": "2024-01-01T00:00:00.000000Z",
     *   "updated_at": "2024-01-15T10:30:00.000000Z"
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     */
    public function me(Request $request): JsonResponse
    {
        return $this->respondOk($request->user());
    }
}
