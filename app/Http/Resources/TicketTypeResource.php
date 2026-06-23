<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ticket_type_id' => $this->ticket_type_id,
            'name' => $this->ticket_type_name,
            'display_name' => $this->ticket_type_name['en'] ?? null,
            'description' => $this->ticket_type_description,
            'price' => $this->price,
            'formatted_price' => number_format($this->price, 2) . ' ETB',
            'capacity' => $this->capacity,
            'sold_count' => $this->sold_count,
            'available_tickets' => $this->available_tickets ?? ($this->capacity - $this->sold_count),
            'is_available' => $this->is_available ?? (($this->capacity - $this->sold_count) > 0),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}