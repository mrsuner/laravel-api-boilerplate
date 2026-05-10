<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin File
 */
class FileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_name' => $this->client_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'checksum' => $this->checksum,
            'visibility' => $this->visibility,
            'meta' => $this->meta,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_claimed' => $this->isClaimed(),
            'download_url' => $request->user()
                ? route('files.download', ['file' => $this->id])
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
