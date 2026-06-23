<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
 
    public function authorize(): bool
    {
      
        return true; // Allow public to create tickets (e.g., through purchase)
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Relationships
            'device_id' => ['nullable', 'string', 'uuid', 'exists:user_devices,device_id'],
            'user_id' => ['nullable', 'string', 'uuid', 'exists:users,user_id'],
            'ticket_type_id' => ['required', 'string', 'uuid', 'exists:ticket_types,ticket_type_id'],
            'concert_id' => ['required', 'string', 'uuid', 'exists:concerts,concert_id'],
            
            // Ticket details
            'order_reference' => ['required', 'string', 'max:50', 'unique:tickets,order_reference'],
            'qr_code' => ['nullable', 'string', 'max:500', 'unique:tickets,qr_code'],
            'ticket_number' => ['nullable', 'string', 'max:50', 'unique:tickets,ticket_number'],
            'price_paid' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            
            // Status
            'status' => ['sometimes', 'string', Rule::in(['active', 'used', 'cancelled', 'refunded'])],
            'purchase_date' => ['sometimes', 'date'],
        ];
    }
     public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user_id = $this->input('user_id');
            $device_id = $this->input('device_id');
           
           
            if (empty($user_id) && empty($device_id)) {
                $validator->errors()->add(
                    'user_id',
                    'Either user_id or device_id must be provided.'
                );
            }
            
          
          
            
           
        });
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
            
            'user_id.required' => 'User ID is required',
            'user_id.uuid' => 'User ID must be a valid UUID',
            'user_id.exists' => 'Selected user does not exist',
            
            'ticket_type_id.required' => 'Ticket type ID is required',
            'ticket_type_id.uuid' => 'Ticket type ID must be a valid UUID',
            'ticket_type_id.exists' => 'Selected ticket type does not exist',
            
            'concert_id.required' => 'Concert ID is required',
            'concert_id.uuid' => 'Concert ID must be a valid UUID',
            'concert_id.exists' => 'Selected concert does not exist',
            
            'order_reference.required' => 'Order reference is required',
            'order_reference.unique' => 'This order reference is already taken',
            'order_reference.max' => 'Order reference cannot exceed 50 characters',
            
            'qr_code.required' => 'QR code is required',
            'qr_code.unique' => 'This QR code is already in use',
            'qr_code.max' => 'QR code cannot exceed 500 characters',
            
            'ticket_number.required' => 'Ticket number is required',
            'ticket_number.unique' => 'This ticket number is already in use',
            'ticket_number.max' => 'Ticket number cannot exceed 50 characters',
            
            'price_paid.required' => 'Price paid is required',
            'price_paid.numeric' => 'Price paid must be a number',
            'price_paid.min' => 'Price paid cannot be negative',
            'price_paid.max' => 'Price paid cannot exceed 999,999.99',
            
            'status.in' => 'Status must be one of: active, used, cancelled, refunded',
            
            'purchase_date.date' => 'Purchase date must be a valid date',
        ];
    }
}