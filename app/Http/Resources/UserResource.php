<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'profile_name' => $this->profile_name,
            'display_name' => $this->profile_name['en'] ?? $this->user_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'profile_picture_url' => $this->profile_picture_url,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'is_active' => $this->status === 1,
            'is_locked' => $this->is_locked ?? false,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(), 
            'role' => new RoleResource($this->whenLoaded('role')),
            'information' => new UserInformationResource($this->whenLoaded('information')),
        ];
    }

    /**
     * Get status label.
     */
    protected function getStatusLabel(): string
    {
        $statuses = [
            1 => 'Active',
            0 => 'Inactive',
            2 => 'Suspended',
            3 => 'Banned',
        ];
        return $statuses[$this->status] ?? 'Unknown';
    }
}