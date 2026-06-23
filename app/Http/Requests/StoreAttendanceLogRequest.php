<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceLogRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        
        return auth()->user() && (auth()->user()->isAdmin() || auth()->user()->isScanner());
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
            'ticket_id' => ['required', 'string', 'uuid', 'exists:tickets,ticket_id'],
            'concert_id' => ['required', 'string', 'uuid', 'exists:concerts,concert_id'],
            'device_id' => ['nullable', 'string', 'uuid', 'exists:user_devices,device_id'],
            'user_id' => ['nullable', 'string', 'uuid', 'exists:users,user_id'],
            
            // Scan details
            'gate_number' => ['nullable', 'string', 'max:50'],
            'scan_time' => ['sometimes', 'date'],
            
            // Status
            'status' => ['required', 'string', Rule::in(['success', 'already_used', 'invalid', 'expired'])],
            'failure_reason' => ['nullable', 'array'],
            'failure_reason.en' => ['nullable', 'string', 'max:500'],
            'failure_reason.am' => ['nullable', 'string', 'max:500'],
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
            'ticket_id.required' => 'Ticket ID is required',
            'ticket_id.uuid' => 'Ticket ID must be a valid UUID',
            'ticket_id.exists' => 'Selected ticket does not exist',
            
            'concert_id.required' => 'Concert ID is required',
            'concert_id.uuid' => 'Concert ID must be a valid UUID',
            'concert_id.exists' => 'Selected concert does not exist',
            
            'device_id.uuid' => 'Device ID must be a valid UUID',
            'device_id.exists' => 'Selected device does not exist',
            
            'user_id.uuid' => 'User ID must be a valid UUID',
            'user_id.exists' => 'Selected user does not exist',
            
            'gate_number.max' => 'Gate number cannot exceed 50 characters',
            
            'scan_time.date' => 'Scan time must be a valid date',
            
            'status.required' => 'Status is required',
            'status.in' => 'Status must be one of: success, already_used, invalid, expired',
            
            'failure_reason.en.max' => 'Failure reason in English cannot exceed 500 characters',
            'failure_reason.am.max' => 'Failure reason in Amharic cannot exceed 500 characters',
        ];
    }
}