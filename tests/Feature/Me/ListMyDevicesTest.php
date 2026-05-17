<?php

namespace Tests\Feature\Me;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListMyDevicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/me/devices')->assertStatus(401);
    }

    public function test_lists_only_the_authenticated_users_devices(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = UserDevice::factory()->for($user)->create();
        UserDevice::factory()->for($other)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/me/devices');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);
    }

    public function test_push_token_is_not_exposed_in_listing(): void
    {
        $user = User::factory()->create();
        UserDevice::factory()->for($user)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/me/devices');

        $this->assertArrayNotHasKey('push_token', $response->json('data.0'));
    }

    public function test_can_filter_by_provider(): void
    {
        $user = User::factory()->create();
        $fcm = UserDevice::factory()->for($user)->fcm()->create();
        UserDevice::factory()->for($user)->expo()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/me/devices?provider=fcm');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$fcm->id], $ids);
    }

    public function test_rejects_unknown_provider_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/me/devices?provider=nope')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_results_are_paginated(): void
    {
        $user = User::factory()->create();
        UserDevice::factory()->for($user)->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/me/devices?per_page=2');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
        $this->assertCount(2, $response->json('data'));
    }
}
