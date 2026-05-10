<?php

namespace Tests\Feature\Files;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.files.enabled', true);
        Storage::fake('local');
    }

    public function test_uploader_can_delete_their_file(): void
    {
        $user = User::factory()->create();
        $path = 'uploads/del.txt';
        Storage::disk('local')->put($path, 'data');

        $file = File::factory()->create([
            'disk' => 'local',
            'path' => $path,
            'uploader_type' => $user->getMorphClass(),
            'uploader_id' => $user->getKey(),
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/files/{$file->id}")
            ->assertNoContent();

        Storage::disk('local')->assertMissing($path);
        $this->assertSoftDeleted('files', ['id' => $file->id]);
    }

    public function test_non_owner_cannot_delete(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $file = File::factory()->create([
            'disk' => 'local',
            'uploader_type' => $owner->getMorphClass(),
            'uploader_id' => $owner->getKey(),
        ]);

        $this->actingAs($stranger)
            ->deleteJson("/api/v1/files/{$file->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('files', ['id' => $file->id, 'deleted_at' => null]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $file = File::factory()->create();

        $this->deleteJson("/api/v1/files/{$file->id}")
            ->assertStatus(401);
    }
}
