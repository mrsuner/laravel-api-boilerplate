<?php

namespace Tests\Feature\Services;

use App\Models\Otp;
use App\Services\Otp\CacheDriver;
use App\Services\Otp\Contracts\OtpService;
use App\Services\Otp\DatabaseDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    // === Database Driver Tests ===

    public function test_database_driver_creates_otp(): void
    {
        config(['boilerplate.auth.otp_driver' => 'database']);

        $service = new DatabaseDriver;
        $token = $service->create('test@example.com');

        $this->assertNotEmpty($token);
        $this->assertEquals(6, strlen($token));
        $this->assertDatabaseHas('otps', [
            'identifier' => 'test@example.com',
            'token' => $token,
        ]);
    }

    public function test_database_driver_verifies_valid_otp(): void
    {
        config(['boilerplate.auth.otp_driver' => 'database']);

        $service = new DatabaseDriver;
        $token = $service->create('test@example.com');

        $result = $service->verify('test@example.com', $token);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('otps', [
            'identifier' => 'test@example.com',
            'token' => $token,
        ]);
    }

    public function test_database_driver_rejects_invalid_otp(): void
    {
        config(['boilerplate.auth.otp_driver' => 'database']);

        $service = new DatabaseDriver;
        $service->create('test@example.com');

        $result = $service->verify('test@example.com', '000000');

        $this->assertFalse($result);
    }

    public function test_database_driver_rejects_expired_otp(): void
    {
        config(['boilerplate.auth.otp_driver' => 'database']);

        Otp::create([
            'identifier' => 'test@example.com',
            'token' => '123456',
            'expires_at' => now()->subMinutes(1),
        ]);

        $service = new DatabaseDriver;
        $result = $service->verify('test@example.com', '123456');

        $this->assertFalse($result);
    }

    public function test_database_driver_deletes_otp(): void
    {
        config(['boilerplate.auth.otp_driver' => 'database']);

        $service = new DatabaseDriver;
        $service->create('test@example.com');

        $this->assertDatabaseHas('otps', ['identifier' => 'test@example.com']);

        $service->delete('test@example.com');

        $this->assertDatabaseMissing('otps', ['identifier' => 'test@example.com']);
    }

    // === Cache Driver Tests ===

    public function test_cache_driver_creates_otp(): void
    {
        config(['boilerplate.auth.otp_driver' => 'cache']);
        config(['boilerplate.auth.otp_cache_store' => 'array']);

        $service = new CacheDriver;
        $token = $service->create('test@example.com');

        $this->assertNotEmpty($token);
        $this->assertEquals(6, strlen($token));

        $cacheKey = 'otp:'.hash('sha256', 'test@example.com');
        $this->assertEquals($token, Cache::store('array')->get($cacheKey));
    }

    public function test_cache_driver_verifies_valid_otp(): void
    {
        config(['boilerplate.auth.otp_driver' => 'cache']);
        config(['boilerplate.auth.otp_cache_store' => 'array']);

        $service = new CacheDriver;
        $token = $service->create('test@example.com');

        $result = $service->verify('test@example.com', $token);

        $this->assertTrue($result);

        $cacheKey = 'otp:'.hash('sha256', 'test@example.com');
        $this->assertNull(Cache::store('array')->get($cacheKey));
    }

    public function test_cache_driver_rejects_invalid_otp(): void
    {
        config(['boilerplate.auth.otp_driver' => 'cache']);
        config(['boilerplate.auth.otp_cache_store' => 'array']);

        $service = new CacheDriver;
        $service->create('test@example.com');

        $result = $service->verify('test@example.com', '000000');

        $this->assertFalse($result);
    }

    public function test_cache_driver_deletes_otp(): void
    {
        config(['boilerplate.auth.otp_driver' => 'cache']);
        config(['boilerplate.auth.otp_cache_store' => 'array']);

        $service = new CacheDriver;
        $service->create('test@example.com');

        $cacheKey = 'otp:'.hash('sha256', 'test@example.com');
        $this->assertNotNull(Cache::store('array')->get($cacheKey));

        $service->delete('test@example.com');

        $this->assertNull(Cache::store('array')->get($cacheKey));
    }

    // === Service Provider Binding Tests ===

    public function test_service_provider_binds_database_driver(): void
    {
        config(['boilerplate.auth.otp_driver' => 'database']);

        $this->app->forgetInstance(OtpService::class);

        $service = $this->app->make(OtpService::class);

        $this->assertInstanceOf(DatabaseDriver::class, $service);
    }

    public function test_service_provider_binds_cache_driver(): void
    {
        config(['boilerplate.auth.otp_driver' => 'cache']);

        $this->app->forgetInstance(OtpService::class);

        $service = $this->app->make(OtpService::class);

        $this->assertInstanceOf(CacheDriver::class, $service);
    }

    // === Integration Tests with Cache Driver ===

    public function test_otp_request_works_with_cache_driver(): void
    {
        config(['boilerplate.auth.otp_driver' => 'cache']);
        config(['boilerplate.auth.otp_cache_store' => 'array']);

        $this->app->forgetInstance(OtpService::class);

        $response = $this->postJson('/api/v1/auth/app/otp', [
            'email' => 'cache-test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'OTP sent to your email.']);

        $cacheKey = 'otp:'.hash('sha256', 'cache-test@example.com');
        $this->assertNotNull(Cache::store('array')->get($cacheKey));
    }

    public function test_otp_verify_works_with_cache_driver(): void
    {
        config(['boilerplate.auth.otp_driver' => 'cache']);
        config(['boilerplate.auth.otp_cache_store' => 'array']);

        $this->app->forgetInstance(OtpService::class);

        $service = $this->app->make(OtpService::class);
        $token = $service->create('cache-verify@example.com');

        $response = $this->postJson('/api/v1/auth/app/otp/verify', [
            'email' => 'cache-verify@example.com',
            'token' => $token,
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'user']]);
    }
}
