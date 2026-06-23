<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'delivery_id' => $this->delivery_id,
            'order_id' => $this->order_id,
            'courier_id' => $this->courier_id,
            'device_id' => $this->device_id,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'delivery_address' => $this->delivery_address,
            'delivery_status' => $this->delivery_status,
            'delivery_note' => $this->delivery_note,
            'assigned_at' => $this->assigned_at?->toDateTimeString(),
            'dispatched_at' => $this->dispatched_at?->toDateTimeString(),
            'delivered_at' => $this->delivered_at?->toDateTimeString(),
            'failed_at' => $this->failed_at?->toDateTimeString(),
            'is_received' => $this->is_received,
            'received_at' => $this->received_at?->toDateTimeString(),
            'confirmed_by' => $this->confirmed_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'order' => new OrderResource($this->whenLoaded('order')),
            'courier' => new UserResource($this->whenLoaded('courier')),
        ];
    }
}
