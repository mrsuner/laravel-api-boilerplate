<?php

namespace App\Services\Otp;

use App\Models\Otp;
use App\Services\Otp\Contracts\OtpService;

class DatabaseDriver implements OtpService
{
    protected int $length;

    protected int $expiryMinutes;

    public function __construct()
    {
        $this->length = config('boilerplate.auth.otp_length', 6);
        $this->expiryMinutes = config('boilerplate.auth.otp_expiry_minutes', 10);
    }

    public function create(string $identifier): string
    {
        $token = $this->generateToken();

        Otp::create([
            'identifier' => $identifier,
            'token' => $token,
            'expires_at' => now()->addMinutes($this->expiryMinutes),
        ]);

        return $token;
    }

    public function verify(string $identifier, string $token): bool
    {
        $otp = Otp::where('identifier', $identifier)
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if ($otp) {
            $otp->delete();

            return true;
        }

        return false;
    }

    public function delete(string $identifier): void
    {
        Otp::where('identifier', $identifier)->delete();
    }

    protected function generateToken(): string
    {
        $min = pow(10, $this->length - 1);
        $max = pow(10, $this->length) - 1;

        return (string) random_int($min, $max);
    }
}
