<?php

namespace Tests\Feature\Files;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.files.enabled', true);
        config()->set('boilerplate.files.disk', 'local');

        Storage::fake('local');
    }

    private function makeFileFor(User $owner, string $visibility = 'private'): File
    {
        $path = 'uploads/'.$owner->getKey().'.txt';
        Storage::disk('local')->put($path, 'hello world');

        return File::factory()->create([
            'disk' => 'local',
            'path' => $path,
            'client_name' => 'hello.txt',
            'uploader_type' => $owner->getMorphClass(),
            'uploader_id' => $owner->getKey(),
            'visibility' => $visibility,
        ]);
    }

    public function test_uploader_can_download_their_file(): void
    {
        $user = User::factory()->create();
        $file = $this->makeFileFor($user);

        $this->actingAs($user)
            ->get("/api/v1/files/{$file->id}/download")
            ->assertOk();
    }

    public function test_other_user_cannot_download_private_file(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $file = $this->makeFileFor($owner, 'private');

        $this->actingAs($stranger)
            ->get("/api/v1/files/{$file->id}/download")
            ->assertStatus(403);
    }

    public function test_any_authenticated_user_can_download_public_file(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $file = $this->makeFileFor($owner, 'public');

        $this->actingAs($stranger)
            ->get("/api/v1/files/{$file->id}/download")
            ->assertOk();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $owner = User::factory()->create();
        $file = $this->makeFileFor($owner);

        $this->getJson("/api/v1/files/{$file->id}/download")
            ->assertStatus(401);
    }

    public function test_show_returns_metadata_for_owner(): void
    {
        $user = User::factory()->create();
        $file = $this->makeFileFor($user);

        $this->actingAs($user)
            ->getJson("/api/v1/files/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $file->id)
            ->assertJsonPath('data.client_name', 'hello.txt');
    }

    public function test_show_rejects_non_owner(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $file = $this->makeFileFor($owner);

        $this->actingAs($stranger)
            ->getJson("/api/v1/files/{$file->id}")
            ->assertStatus(403);
    }
}
