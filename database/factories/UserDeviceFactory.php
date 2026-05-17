<?php

namespace Database\Factories;

use App\Enums\DevicePlatform;
use App\Enums\PushProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\UserDevice>
 */
class UserDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => fake()->randomElement(DevicePlatform::cases()),
            'provider' => PushProvider::Fcm,
            'push_token' => Str::random(64),
            'device_id' => fake()->uuid(),
            'device_name' => fake()->randomElement(['iPhone 15', 'Pixel 8', 'Chrome on macOS']),
            'app_version' => fake()->numerify('#.#.#'),
            'last_used_at' => now(),
        ];
    }

    public function fcm(): static
    {
        return $this->state(fn (): array => ['provider' => PushProvider::Fcm]);
    }

    public function expo(): static
    {
        return $this->state(fn (): array => [
            'provider' => PushProvider::Expo,
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
        ]);
    }
}
