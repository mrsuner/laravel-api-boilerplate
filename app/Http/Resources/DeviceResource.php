<?php

namespace App\Http\Resources;

use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserDevice
 */
class DeviceResource extends JsonResource
{
    /**
     * The raw push_token is a sending credential and is intentionally never
     * serialized — clients already hold their own token.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'provider' => $this->provider->value,
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'app_version' => $this->app_version,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
