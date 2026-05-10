<?php

namespace Database\Factories;

use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['jpg', 'png', 'pdf', 'txt']);
        $name = fake()->slug(2).'.'.$extension;

        return [
            'disk' => 'local',
            'path' => 'uploads/'.Str::ulid().'.'.$extension,
            'client_name' => $name,
            'mime_type' => match ($extension) {
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'pdf' => 'application/pdf',
                default => 'text/plain',
            },
            'extension' => $extension,
            'size' => fake()->numberBetween(1024, 5_000_000),
            'visibility' => 'private',
            'expires_at' => now()->addDay(),
        ];
    }

    /**
     * Mark the file as claimed (no TTL).
     */
    public function claimed(): static
    {
        return $this->state(fn (): array => ['expires_at' => null]);
    }

    /**
     * Mark the file as already expired.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    /**
     * Mark the file as anonymous (no uploader).
     */
    public function anonymous(): static
    {
        return $this->state(fn (): array => [
            'uploader_type' => null,
            'uploader_id' => null,
        ]);
    }
}
