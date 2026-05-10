<?php

namespace Tests\Feature\Files;

use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('boilerplate.files.cleanup.enabled', true);
        Storage::fake('local');
    }

    private function makeFile(?string $expiresAt, string $path = 'uploads/test.txt'): File
    {
        Storage::disk('local')->put($path, 'data');

        return File::factory()->create([
            'disk' => 'local',
            'path' => $path,
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_removes_expired_files_from_disk_and_database(): void
    {
        $expired = $this->makeFile(now()->subMinute()->toDateTimeString(), 'uploads/expired.txt');

        $this->artisan('files:cleanup')->assertSuccessful();

        $this->assertDatabaseMissing('files', ['id' => $expired->id]);
        Storage::disk('local')->assertMissing('uploads/expired.txt');
    }

    public function test_leaves_claimed_files_alone(): void
    {
        $claimed = $this->makeFile(null, 'uploads/keep.txt');

        $this->artisan('files:cleanup')->assertSuccessful();

        $this->assertDatabaseHas('files', ['id' => $claimed->id]);
        Storage::disk('local')->assertExists('uploads/keep.txt');
    }

    public function test_leaves_not_yet_expired_files_alone(): void
    {
        $live = $this->makeFile(now()->addHour()->toDateTimeString(), 'uploads/live.txt');

        $this->artisan('files:cleanup')->assertSuccessful();

        $this->assertDatabaseHas('files', ['id' => $live->id]);
        Storage::disk('local')->assertExists('uploads/live.txt');
    }

    public function test_dry_run_does_not_delete(): void
    {
        $expired = $this->makeFile(now()->subMinute()->toDateTimeString(), 'uploads/dry.txt');

        $this->artisan('files:cleanup', ['--dry-run' => true])
            ->expectsOutputToContain('would delete')
            ->assertSuccessful();

        $this->assertDatabaseHas('files', ['id' => $expired->id]);
        Storage::disk('local')->assertExists('uploads/dry.txt');
    }

    public function test_disabled_cleanup_is_a_noop(): void
    {
        config()->set('boilerplate.files.cleanup.enabled', false);

        $expired = $this->makeFile(now()->subMinute()->toDateTimeString(), 'uploads/skip.txt');

        $this->artisan('files:cleanup')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        $this->assertDatabaseHas('files', ['id' => $expired->id]);
        Storage::disk('local')->assertExists('uploads/skip.txt');
    }

    public function test_cleanup_force_deletes_soft_deleted_expired_rows(): void
    {
        $expired = $this->makeFile(now()->subMinute()->toDateTimeString(), 'uploads/soft.txt');
        $expired->delete();

        $this->artisan('files:cleanup')->assertSuccessful();

        $this->assertDatabaseMissing('files', ['id' => $expired->id]);
        Storage::disk('local')->assertMissing('uploads/soft.txt');
    }
}
