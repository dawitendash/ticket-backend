<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'attendance_log_id' => $this->attendance_log_id,
            'ticket_id' => $this->ticket_id,
            'concert_id' => $this->concert_id,
            'user_id' => $this->user_id,
            'gate_number' => $this->gate_number,
            'scan_time' => $this->scan_time?->toDateTimeString(),
            'formatted_scan_time' => $this->formatted_scan_time ?? $this->scan_time?->format('F j, Y, g:i A'),
            'status' => $this->status,
            'status_label' => $this->status_label ?? ucfirst(str_replace('_', ' ', $this->status)),
            'status_color' => $this->status_color ?? $this->getStatusColor(),
            'is_success' => $this->is_success ?? ($this->status === 'success'),
            'failure_reason' => $this->failure_reason,
            'failure_reason_localized' => $this->failure_reason_localized ?? $this->failure_reason['en'] ?? null,
            
            'ticket' => new TicketResource($this->whenLoaded('ticket')),
            'concert' => new ConcertResource($this->whenLoaded('concert')),
            'scanner' => new UserResource($this->whenLoaded('scanner')),
        ];
    }

    /**
     * Get status color.
     */
    protected function getStatusColor(): string
    {
        $colors = [
            'success' => 'green',
            'already_used' => 'yellow',
            'invalid' => 'red',
            'expired' => 'blue',
        ];
        return $colors[$this->status] ?? 'gray';
    }
}