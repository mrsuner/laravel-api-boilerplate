<?php

namespace App\Services\Otp\Contracts;

interface OtpService
{
    /**
     * Create and store a new OTP for the given identifier.
     * Returns the generated OTP token.
     */
    public function create(string $identifier): string;

    /**
     * Verify an OTP token for the given identifier.
     * Returns true if valid and not expired, false otherwise.
     * Deletes the OTP after successful verification.
     */
    public function verify(string $identifier, string $token): bool;

    /**
     * Delete any existing OTP for the given identifier.
     */
    public function delete(string $identifier): void;
}
