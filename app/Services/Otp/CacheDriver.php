<?php

namespace App\Services\Otp;

use App\Services\Otp\Contracts\OtpService;
use Illuminate\Support\Facades\Cache;

class CacheDriver implements OtpService
{
    protected int $length;

    protected int $expiryMinutes;

    protected ?string $store;

    public function __construct()
    {
        $this->length = config('boilerplate.auth.otp_length', 6);
        $this->expiryMinutes = config('boilerplate.auth.otp_expiry_minutes', 10);
        $this->store = config('boilerplate.auth.otp_cache_store');
    }

    public function create(string $identifier): string
    {
        $token = $this->generateToken();
        $key = $this->getCacheKey($identifier);

        Cache::store($this->store)->put(
            $key,
            $token,
            now()->addMinutes($this->expiryMinutes)
        );

        return $token;
    }

    public function verify(string $identifier, string $token): bool
    {
        $key = $this->getCacheKey($identifier);
        $storedToken = Cache::store($this->store)->get($key);

        if ($storedToken && hash_equals($storedToken, $token)) {
            Cache::store($this->store)->forget($key);

            return true;
        }

        return false;
    }

    public function delete(string $identifier): void
    {
        $key = $this->getCacheKey($identifier);
        Cache::store($this->store)->forget($key);
    }

    protected function generateToken(): string
    {
        $min = pow(10, $this->length - 1);
        $max = pow(10, $this->length) - 1;

        return (string) random_int($min, $max);
    }

    protected function getCacheKey(string $identifier): string
    {
        return 'otp:'.hash('sha256', $identifier);
    }
}
