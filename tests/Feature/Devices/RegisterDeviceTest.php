<?php

namespace Tests\Feature\Devices;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterDeviceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'platform' => 'ios',
            'provider' => 'fcm',
            'push_token' => 'token-abc-123',
            'device_name' => "John's iPhone",
            'app_version' => '1.4.2',
        ], $overrides);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/devices', $this->payload())->assertStatus(401);
    }

    public function test_authenticated_user_can_register_a_device(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/devices', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.platform', 'ios')
            ->assertJsonPath('data.provider', 'fcm')
            ->assertJsonPath('data.device_name', "John's iPhone");

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->getKey(),
            'push_token' => 'token-abc-123',
            'provider' => 'fcm',
        ]);
    }

    public function test_push_token_is_never_returned(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/devices', $this->payload());

        $this->assertArrayNotHasKey('push_token', $response->json('data'));
    }

    public function test_re_registering_same_token_updates_in_place(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/devices', $this->payload([
            'device_name' => 'Old name',
        ]))->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/devices', $this->payload([
            'device_name' => 'New name',
        ]))->assertCreated();

        $this->assertSame(1, UserDevice::query()->where('push_token', 'token-abc-123')->count());
        $this->assertDatabaseHas('user_devices', [
            'push_token' => 'token-abc-123',
            'device_name' => 'New name',
        ]);
    }

    public function test_token_is_transferred_when_registered_by_another_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->postJson('/api/v1/devices', $this->payload())->assertCreated();
        $this->actingAs($second)->postJson('/api/v1/devices', $this->payload())->assertCreated();

        $this->assertSame(1, UserDevice::query()->where('push_token', 'token-abc-123')->count());
        $this->assertDatabaseHas('user_devices', [
            'push_token' => 'token-abc-123',
            'user_id' => $second->getKey(),
        ]);
        $this->assertSame(0, $first->devices()->count());
    }

    public function test_validation_rejects_unknown_platform_and_provider(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/devices', $this->payload([
                'platform' => 'windows-phone',
                'provider' => 'carrier-pigeon',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['platform', 'provider']);
    }

    public function test_push_token_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/devices', $this->payload(['push_token' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['push_token']);
    }

    public function test_endpoint_404s_when_push_module_disabled(): void
    {
        config(['boilerplate.push.enabled' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/devices', $this->payload())
            ->assertStatus(404);
    }
}
