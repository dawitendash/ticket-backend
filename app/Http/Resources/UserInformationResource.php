<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserInformationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_information_id' => $this->user_information_id,
            'user_id' => $this->user_id,
            'full_name' => $this->full_name,
            'display_name' => $this->full_name['en'] ?? null,
            'phone_number' => $this->phone_number,
            'national_id_number' => $this->national_id_number,
            'national_id_front_image' => $this->national_id_front_image,
            'national_id_back_image' => $this->national_id_back_image,
            'has_national_id' => $this->has_national_id ?? (!empty($this->national_id_number)),
            'is_complete' => $this->isComplete(),
            'address' => $this->address,
            'display_address' => $this->address['en'] ?? null,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}