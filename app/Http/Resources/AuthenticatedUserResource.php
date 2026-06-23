<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class AuthenticatedUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->user_id,
                'user_name' => $this->user_name,
                'profile_name' => $this->profile_name,
                'display_name' => $this->display_name ?? $this->user_name,
                'email' => $this->email,
                'phone_number' => $this->phone_number,
                'profile_picture_url' => $this->resolveProfilePictureUrl(),
                'status' => $this->getStatusValue(),
                'status_label' => $this->getStatusLabel(),
                'lang' => $this->lang ?? 'en',
                'email_verified_at' => $this->email_verified_at?->toDateTimeString(),
                'last_login' => $this->last_login?->toDateTimeString(),
                'last_active_at' => $this->last_active_at?->toDateTimeString(),
                'is_locked' => $this->is_locked ?? false,
                'locked_until' => $this->locked_until?->toDateTimeString(),
                'is_online' => $this->is_online ?? false,
                'created_at' => $this->created_at?->toDateTimeString(),
                'updated_at' => $this->updated_at?->toDateTimeString(),
            ],
            'role' => $this->whenLoaded('role', fn() => [
                'id' => $this->role->role_id,
                'name' => $this->role->name,
                'display_name' => $this->role->name['en'] ?? null,
                'slug' => $this->role->slug,
            ]), 
            'user_information' => $this->whenLoaded('information', fn() => [
                'full_name' => $this->information->full_name,
                'display_name' => $this->information->full_name['en'] ?? null,
                'phone_number' => $this->information->phone_number,
                'national_id_number' => $this->information->national_id_number,
                'has_national_id' => $this->information->has_national_id ?? false,
                'is_complete' => $this->information->isComplete(),
                'address' => $this->information->address,
                'gate_number' => $this->gate_number,
            ]),
            'devices' => $this->whenLoaded('devices', fn() => $this->devices->map(fn($device) => [
                'id' => $device->device_id,
                'name' => $device->device_name,
                'type' => $device->device_type,
                'platform' => $device->platform,
                'is_trusted' => $device->is_trusted,
                'is_online' => $device->is_online ?? false,
                'last_active' => $device->last_active_at?->toDateTimeString(),
            ])),  
        ];

        
    }
     public function getStatusValue(): int
    {
        if ($this->status instanceof \App\Enums\UserStatus) {
            return $this->status->value;
        }
        return (int) ($this->status ?? 1);
    }

    /**
     * Get status label.
     */
    public function getStatusLabel(): string
    {
        if (isset($this->status_label)) {
            return $this->status_label;
        }
        
        $statuses = [
            0 => 'Inactive',
            1 => 'Active',
            2 => 'Suspended',
            3 => 'Banned',
        ];
        return $statuses[$this->status] ?? 'Unknown';
    }

    /**
     * Resolve the current service provider ID from request input/header/session.
     */
    

    /**
     * Return a browser-ready profile picture URL while preserving old external URLs.
     */
    protected function resolveProfilePictureUrl(): ?string
    {
        if (! $this->profile_picture_url) {
            return null;
        }

        if (filter_var($this->profile_picture_url, FILTER_VALIDATE_URL)) {
            return $this->profile_picture_url;
        }

        if (str_starts_with($this->profile_picture_url, '/storage/')) {
            return asset(ltrim($this->profile_picture_url, '/'));
        }

        return asset('storage/' . ltrim($this->profile_picture_url, '/'));
    }

    

    

 
 
}
