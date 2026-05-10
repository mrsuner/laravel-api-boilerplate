<?php

namespace Tests\Feature\Me;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListMyFilesTest extends TestCase
{
    use RefreshDatabase;

    private function fileFor(User $user, array $overrides = []): File
    {
        return File::factory()->create(array_merge([
            'uploader_type' => $user->getMorphClass(),
            'uploader_id' => $user->getKey(),
            'expires_at' => null,
        ], $overrides));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/me/files')->assertStatus(401);
    }

    public function test_lists_only_claimed_files_for_authenticated_user_by_default(): void
    {
        $user = User::factory()->create();
        $claimed = $this->fileFor($user, ['expires_at' => null]);
        $this->fileFor($user, ['expires_at' => now()->addHour()]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/files');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($claimed->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_does_not_list_other_users_files(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->fileFor($user);
        $this->fileFor($other);

        $response = $this->actingAs($user)->getJson('/api/v1/me/files');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);
    }

    public function test_does_not_list_anonymous_files(): void
    {
        $user = User::factory()->create();
        $mine = $this->fileFor($user);
        File::factory()->anonymous()->claimed()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/me/files');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);
    }

    public function test_claimed_false_returns_only_ttl_files(): void
    {
        $user = User::factory()->create();
        $this->fileFor($user, ['expires_at' => null]);
        $pending = $this->fileFor($user, ['expires_at' => now()->addHour()]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/files?claimed=false');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$pending->id], $ids);
    }

    public function test_claimed_accepts_string_boolean_variants(): void
    {
        $user = User::factory()->create();
        $this->fileFor($user, ['expires_at' => null]);
        $pending = $this->fileFor($user, ['expires_at' => now()->addHour()]);

        foreach (['false', '0', 'off', 'no'] as $variant) {
            $response = $this->actingAs($user)->getJson('/api/v1/me/files?claimed='.$variant);
            $response->assertOk();
            $this->assertSame(
                [$pending->id],
                collect($response->json('data'))->pluck('id')->all(),
                'claimed='.$variant
            );
        }
    }

    public function test_invalid_claimed_value_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/me/files?claimed=banana')
            ->assertStatus(422);
    }

    public function test_filters_by_visibility(): void
    {
        $user = User::factory()->create();
        $public = $this->fileFor($user, ['visibility' => 'public']);
        $this->fileFor($user, ['visibility' => 'private']);

        $response = $this->actingAs($user)->getJson('/api/v1/me/files?visibility=public');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$public->id], $ids);
    }

    public function test_searches_client_name_substring(): void
    {
        $user = User::factory()->create();
        $invoice = $this->fileFor($user, ['client_name' => 'invoice-2026.pdf']);
        $this->fileFor($user, ['client_name' => 'photo.jpg']);

        $response = $this->actingAs($user)->getJson('/api/v1/me/files?q=invoice');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$invoice->id], $ids);
    }

    public function test_sorts_by_size_ascending(): void
    {
        $user = User::factory()->create();
        $small = $this->fileFor($user, ['size' => 100]);
        $large = $this->fileFor($user, ['size' => 9999]);
        $mid = $this->fileFor($user, ['size' => 1000]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/files?sort=size');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$small->id, $mid->id, $large->id], $ids);
    }

    public function test_sorts_by_size_descending(): void
    {
        $user = User::factory()->create();
        $small = $this->fileFor($user, ['size' => 100]);
        $large = $this->fileFor($user, ['size' => 9999]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/files?sort=-size');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$large->id, $small->id], $ids);
    }

    public function test_default_sort_is_newest_first(): void
    {
        $user = User::factory()->create();
        $older = $this->fileFor($user, ['created_at' => now()->subDay()]);
        $newer = $this->fileFor($user, ['created_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/files');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_per_page_is_clamped_and_returns_pagination_envelope(): void
    {
        $user = User::factory()->create();
        File::factory()
            ->count(5)
            ->state([
                'uploader_type' => $user->getMorphClass(),
                'uploader_id' => $user->getKey(),
                'expires_at' => null,
            ])
            ->create();

        $response = $this->actingAs($user)->getJson('/api/v1/me/files?per_page=2');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(2, 'data');
    }

    public function test_invalid_sort_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/me/files?sort=banana')
            ->assertStatus(422);
    }

    public function test_invalid_visibility_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/me/files?visibility=secret')
            ->assertStatus(422);
    }
}
