<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        
        return auth()->user() && auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ticketTypeId = $this->route('ticket_type_id');
        
        return [
            // Concert relationship (can be updated)
            'concert_id' => ['sometimes', 'string', 'uuid', 'exists:concerts,concert_id'],
            
            // Ticket type name
            'ticket_type_name' => ['sometimes', 'array'],
            'ticket_type_name.en' => ['sometimes', 'string', 'max:100'],
            'ticket_type_name.am' => ['nullable', 'string', 'max:100'],
            
            // Ticket type description
            'ticket_type_description' => ['nullable', 'array'],
            'ticket_type_description.en' => ['nullable', 'string', 'max:500'],
            'ticket_type_description.am' => ['nullable', 'string', 'max:500'],
            
            // Pricing and capacity
            'price' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'sold_count' => ['sometimes', 'integer', 'min:0', 'max:capacity'],
            
            // Status
            'is_active' => ['sometimes', 'boolean'],
            
            // For bulk operations
            'adjust_sold_count' => ['sometimes', 'integer', 'min:-1000', 'max:1000'],
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
            'concert_id.uuid' => 'Concert ID must be a valid UUID',
            'concert_id.exists' => 'Selected concert does not exist',
            
            'ticket_type_name.en.max' => 'Ticket type name in English cannot exceed 100 characters',
            'ticket_type_name.am.max' => 'Ticket type name in Amharic cannot exceed 100 characters',
            
            'ticket_type_description.en.max' => 'Ticket type description in English cannot exceed 500 characters',
            'ticket_type_description.am.max' => 'Ticket type description in Amharic cannot exceed 500 characters',
            
            'price.numeric' => 'Price must be a number',
            'price.min' => 'Price cannot be negative',
            'price.max' => 'Price cannot exceed 999,999.99',
            
            'capacity.integer' => 'Capacity must be a number',
            'capacity.min' => 'Capacity must be at least 1',
            'capacity.max' => 'Capacity cannot exceed 100,000',
            
            'sold_count.integer' => 'Sold count must be a number',
            'sold_count.min' => 'Sold count cannot be negative',
            'sold_count.max' => 'Sold count cannot exceed capacity',
            
            'is_active.boolean' => 'Active status must be true or false',
            
            'adjust_sold_count.integer' => 'Adjust sold count must be a number',
            'adjust_sold_count.min' => 'Adjust sold count cannot be less than -1000',
            'adjust_sold_count.max' => 'Adjust sold count cannot exceed 1000',
        ];
    }
}