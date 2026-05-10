<?php

namespace Tests\Feature\Files;

use App\Models\File;
use App\Services\Files\FileService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_clears_expires_at_on_model(): void
    {
        $file = File::factory()->create(['expires_at' => now()->addHour()]);

        $file->claim();

        $this->assertNull($file->fresh()->expires_at);
        $this->assertTrue($file->fresh()->isClaimed());
    }

    public function test_claim_is_idempotent(): void
    {
        $file = File::factory()->claimed()->create();

        $file->claim();

        $this->assertNull($file->fresh()->expires_at);
    }

    public function test_release_re_attaches_expires_at(): void
    {
        $file = File::factory()->claimed()->create();

        $file->release(60);

        $expires = $file->fresh()->expires_at;
        $this->assertNotNull($expires);
        $this->assertEqualsWithDelta(now()->addMinutes(60)->getTimestamp(), $expires->getTimestamp(), 5);
    }

    public function test_service_claim_resolves_by_id(): void
    {
        $file = File::factory()->create(['expires_at' => now()->addHour()]);

        app(FileService::class)->claim($file->id);

        $this->assertNull($file->fresh()->expires_at);
    }

    public function test_service_claim_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(FileService::class)->claim('01HXNONEXISTENTULIDXXXXXXX');
    }

    public function test_service_claim_many_handles_mixed_inputs(): void
    {
        $file1 = File::factory()->create(['expires_at' => now()->addHour()]);
        $file2 = File::factory()->create(['expires_at' => now()->addHour()]);

        app(FileService::class)->claimMany([$file1, $file2->id]);

        $this->assertNull($file1->fresh()->expires_at);
        $this->assertNull($file2->fresh()->expires_at);
    }
}
