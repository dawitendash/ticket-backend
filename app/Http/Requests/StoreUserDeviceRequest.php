<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ✅ Allow everyone (including unauthenticated)
    }

    public function rules(): array
    {
        $rules = [
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string'],
            'platform' => ['nullable', 'string'],
            'device_token' => ['nullable', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string'],
            'is_trusted' => ['sometimes', 'boolean'],
            'user_id' => ['nullable', 'string', 'exists:users,user_id'],
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'device_type.in' => 'Device type must be: mobile, web, tablet, desktop, or other',
            'platform.in' => 'Platform must be: ios, android, windows, mac, linux, or web',
            'user_id.exists' => 'User not found with the provided user_id',
        ];
    }

    protected function prepareForValidation(): void
    {
        // If authenticated, automatically set user_id
        if (auth()->check()) {
            $this->merge([
                'user_id' => auth()->id(),
            ]);
        }
    }
}