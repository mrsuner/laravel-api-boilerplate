<?php

namespace Tests\Feature\Devices;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnregisterDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $device = UserDevice::factory()->create();

        $this->deleteJson("/api/v1/devices/{$device->id}")->assertStatus(401);
    }

    public function test_owner_can_unregister_their_device(): void
    {
        $user = User::factory()->create();
        $device = UserDevice::factory()->for($user)->create();

        $this->actingAs($user)
            ->deleteJson("/api/v1/devices/{$device->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('user_devices', ['id' => $device->id]);
    }

    public function test_user_cannot_unregister_another_users_device(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $device = UserDevice::factory()->for($other)->create();

        $this->actingAs($user)
            ->deleteJson("/api/v1/devices/{$device->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('user_devices', ['id' => $device->id]);
    }

    public function test_unknown_device_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/api/v1/devices/01HXMISSINGMISSINGMISSING')
            ->assertStatus(404);
    }
}
