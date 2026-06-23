<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'device_id' => $this->device_id,
            'user_id' => $this->user_id,
            'device_name' => $this->device_name,
            'device_type' => $this->device_type,
            'device_type_label' => $this->device_type_label ?? ucfirst($this->device_type ?? 'Unknown'),
            'platform' => $this->platform,
            'platform_label' => $this->platform_label ?? ucfirst($this->platform ?? 'Unknown'),
            'is_trusted' => $this->is_trusted,
            'is_online' => $this->is_online ?? false,
            'last_active_at' => $this->last_active_at?->toDateTimeString(),
            'last_active_ago' => $this->last_active_ago ?? $this->last_active_at?->diffForHumans(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}