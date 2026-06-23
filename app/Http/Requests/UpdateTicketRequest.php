<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ticketId = $this->route('ticket_id');
        
        return [
            // Relationships
            'device_id' => ['nullable', 'string', 'uuid', 'exists:user_devices,device_id'],
            'user_id' => ['nullable', 'string', 'uuid', 'exists:users,user_id'],
            'ticket_type_id' => ['nullable', 'string', 'uuid', 'exists:ticket_types,ticket_type_id'],
            'concert_id' => ['nullable', 'string', 'uuid', 'exists:concerts,concert_id'],
            
            // Ticket details
            'order_reference' => ['nullable', 'string', 'max:50', Rule::unique('tickets', 'order_reference')->ignore($ticketId, 'ticket_id')],
            'qr_code' => ['nullable', 'string', 'max:500', Rule::unique('tickets', 'qr_code')->ignore($ticketId, 'ticket_id')],
            'ticket_number' => ['nullable', 'string', 'max:50', Rule::unique('tickets', 'ticket_number')->ignore($ticketId, 'ticket_id')],
            'price_paid' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            
            // Status
            'status' => ['nullable', 'string', Rule::in(['active', 'used', 'cancelled', 'refunded'])],
            'purchase_date' => ['nullable', 'date'],
            
            // Admin actions
            'mark_as_used' => ['sometimes', 'boolean'],
            'mark_as_cancelled' => ['sometimes', 'boolean'],
            'mark_as_refunded' => ['sometimes', 'boolean'],
            'reactivate' => ['sometimes', 'boolean'],
            
            // Force delete
            'force_delete' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the validation messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_id.uuid' => 'Device ID must be a valid UUID',
            'device_id.exists' => 'Selected device does not exist',
            
            'user_id.uuid' => 'User ID must be a valid UUID',
            'user_id.exists' => 'Selected user does not exist',
            
            'ticket_type_id.uuid' => 'Ticket type ID must be a valid UUID',
            'ticket_type_id.exists' => 'Selected ticket type does not exist',
            
            'concert_id.uuid' => 'Concert ID must be a valid UUID',
            'concert_id.exists' => 'Selected concert does not exist',
            
            'order_reference.unique' => 'This order reference is already taken',
            'order_reference.max' => 'Order reference cannot exceed 50 characters',
            
            'qr_code.unique' => 'This QR code is already in use',
            'qr_code.max' => 'QR code cannot exceed 500 characters',
            
            'ticket_number.unique' => 'This ticket number is already in use',
            'ticket_number.max' => 'Ticket number cannot exceed 50 characters',
            
            'price_paid.numeric' => 'Price paid must be a number',
            'price_paid.min' => 'Price paid cannot be negative',
            'price_paid.max' => 'Price paid cannot exceed 999,999.99',
            
            'status.in' => 'Status must be one of: active, used, cancelled, refunded',
            
            'purchase_date.date' => 'Purchase date must be a valid date',
            
            'mark_as_used.boolean' => 'Mark as used must be true or false',
            'mark_as_cancelled.boolean' => 'Mark as cancelled must be true or false',
            'mark_as_refunded.boolean' => 'Mark as refunded must be true or false',
            'reactivate.boolean' => 'Reactivate must be true or false',
            
            'force_delete.boolean' => 'Force delete must be true or false',
        ];
    }
}