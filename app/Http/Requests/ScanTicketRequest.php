<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users with scanner or admin role can scan
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'qr_code' => ['required', 'string', 'max:500'],
            'device_id' => ['nullable', 'string', 'exists:user_devices,device_id'],
            'gate_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'qr_code.required' => 'QR code is required to scan a ticket.',
            'qr_code.string' => 'QR code must be a valid string.',
            'qr_code.max' => 'QR code cannot exceed 500 characters.',
            'device_id.exists' => 'Selected device does not exist.',
            'gate_number.max' => 'Gate number cannot exceed 50 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If user is authenticated and no device_id provided, use their device
        if (auth()->check() && !$this->has('device_id')) {
            // Try to get the user's default device
            $device = \App\Models\UserDevice::where('user_id', auth()->id())
                ->where('is_trusted', true)
                ->first();
            
            if ($device) {
                $this->merge([
                    'device_id' => $device->device_id,
                ]);
            }
        }
    }
}