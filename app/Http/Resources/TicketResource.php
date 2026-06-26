<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ticket_id' => $this->ticket_id,
            'ticket_number' => $this->ticket_number,
            'order_reference' => $this->order_reference,
            'price_paid' => $this->price_paid,
            'formatted_price' => $this->formatted_price ?? number_format($this->price_paid, 2) . ' ETB',
            'status' => $this->status,
            'status_label' => $this->status_label ?? ucfirst($this->status),
            'status_color' => $this->status_color ?? $this->getStatusColor(),
            'is_active' => $this->is_active ?? ($this->status === 'active'),
            'qr_code' => $this->qr_code,
            'purchase_date' => $this->purchase_date?->toDateTimeString(),
            'formatted_purchase_date' => $this->formatted_purchase_date ?? $this->purchase_date?->format('F j, Y, g:i A'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
             
            'ticket_type' => new TicketTypeResource($this->whenLoaded('ticketType')),
            'concert' => new ConcertResource($this->whenLoaded('concert')),
            'user' => new UserResource($this->whenLoaded('user')),
            'user_information' => new UserInformationResource($this->whenLoaded('userInformation')),
            'device' => new UserDeviceResource($this->whenLoaded('device')),
            'attendance_logs' => AttendanceLogResource::collection($this->whenLoaded('attendanceLogs')),
        ];
    }

    /**
     * Get status color.
     */
    protected function getStatusColor(): string
    {
        $colors = [
            'active' => 'green',
            'used' => 'blue',
            'cancelled' => 'red',
            'refunded' => 'blue',
        ];
        return $colors[$this->status] ?? 'gray';
    }
}