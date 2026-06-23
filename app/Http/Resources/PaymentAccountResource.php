<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payment_account_id' => $this->payment_account_id,
            'user_id' => $this->user_id,
            'account_type' => $this->account_type,
            'account_type_label' => $this->account_type_label,
            'owner_name' => $this->owner_name,
            'account_identifier' => $this->account_identifier,
            'masked_account' => $this->masked_account,
            'provider' => $this->provider,
            'last_four' => $this->last_four,
            'expiry_month' => $this->expiry_month,
            'expiry_year' => $this->expiry_year,
            'meta' => $this->meta,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'status_label' => $this->status_label,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            
          //  'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}