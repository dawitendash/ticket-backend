<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConcertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'concert_id' => $this->concert_id,
            'name' => $this->name,
            'display_name' => $this->name['en'] ?? null,
            'artist' => $this->artist,
            'display_artist' => $this->artist['en'] ?? null,
            'venue' => $this->venue,
            'display_venue' => $this->venue['en'] ?? null,
            'description' => $this->description,
            'concert_date' => $this->concert_date?->toDateTimeString(),
            'formatted_concert_date' => $this->formatted_concert_date ?? $this->concert_date?->format('F j, Y, g:i A'),
            'door_open_time' => $this->door_open_time?->toDateTimeString(),
            'formatted_door_open_time' => $this->formatted_door_open_time ?? $this->door_open_time?->format('F j, Y, g:i A'),
            'status' => $this->status,
            'status_label' => $this->status_label ?? ucfirst($this->status),
            'status_color' => $this->status_color ?? $this->getStatusColor(),
            'max_capacity' => $this->max_capacity, 
            'is_sold_out' => $this->is_sold_out ?? ($this->available_tickets <= 0),
            'image_url' => $this->image_url,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            
            // Relationships
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
        ];
    }

   

    /**
     * Get status color.
     */
    protected function getStatusColor(): string
    {
        $colors = [
            'upcoming' => 'blue',
            'ongoing' => 'green',
            'completed' => 'gray',
            'cancelled' => 'red',
        ];
        return $colors[$this->status] ?? 'gray';
    }
}