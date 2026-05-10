<?php

namespace Tests\Feature\Files;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File as TestFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.files.enabled', true);
        config()->set('boilerplate.files.disk', 'local');
        config()->set('boilerplate.files.allow_anonymous_upload', false);
        config()->set('boilerplate.files.default_expires_after_minutes', 1440);

        Storage::fake('local');
    }

    public function test_authenticated_user_can_upload_a_file(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/files', [
                'file' => TestFile::create('hello.txt', 5),
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'client_name', 'mime_type', 'size', 'expires_at', 'is_claimed']]);

        $file = File::query()->firstOrFail();
        $this->assertSame('hello.txt', $file->client_name);
        $this->assertSame($user->getKey(), $file->uploader_id);
        $this->assertSame($user->getMorphClass(), $file->uploader_type);
        $this->assertNotNull($file->expires_at);
        $this->assertFalse($file->isClaimed());
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_anonymous_upload_rejected_when_disabled(): void
    {
        config()->set('boilerplate.files.allow_anonymous_upload', false);

        $this->postJson('/api/v1/files', [
            'file' => TestFile::create('hello.txt', 5),
        ])->assertStatus(401);

        $this->assertSame(0, File::query()->count());
    }

    public function test_anonymous_upload_allowed_when_enabled(): void
    {
        config()->set('boilerplate.files.allow_anonymous_upload', true);

        $this->postJson('/api/v1/files', [
            'file' => TestFile::create('anon.txt', 5),
        ])->assertCreated();

        $file = File::query()->firstOrFail();
        $this->assertNull($file->uploader_id);
        $this->assertNull($file->uploader_type);
    }

    public function test_upload_rejects_files_over_max_size(): void
    {
        config()->set('boilerplate.files.max_size_kb', 1);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/files', [
                'file' => TestFile::create('big.txt', 2048),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_disallowed_extension(): void
    {
        config()->set('boilerplate.files.allowed_extensions', ['png']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/files', [
                'file' => TestFile::create('script.exe', 1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_default_expires_at_matches_config(): void
    {
        config()->set('boilerplate.files.default_expires_after_minutes', 60);

        $user = User::factory()->create();

        $before = now();
        $this->actingAs($user)
            ->postJson('/api/v1/files', [
                'file' => TestFile::create('a.txt', 1),
            ])->assertCreated();

        $file = File::query()->firstOrFail();
        $this->assertNotNull($file->expires_at);
        $this->assertEqualsWithDelta(
            $before->copy()->addMinutes(60)->getTimestamp(),
            $file->expires_at->getTimestamp(),
            5,
        );
    }

    public function test_upload_returns_404_when_module_disabled(): void
    {
        config()->set('boilerplate.files.enabled', false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/files', [
                'file' => TestFile::create('x.txt', 1),
            ])->assertStatus(404);
    }

    public function test_upload_accepts_optional_visibility_and_meta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/files', [
                'file' => TestFile::create('a.txt', 1),
                'visibility' => 'public',
                'meta' => ['source' => 'web'],
            ])->assertCreated();

        $file = File::query()->firstOrFail();
        $this->assertSame('public', $file->visibility);
        $this->assertSame(['source' => 'web'], $file->meta);
    }
}
