<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'device_name' => ['sometimes', 'string', 'max:255'],
            'device_type' => ['sometimes', 'string', Rule::in(['mobile', 'web', 'tablet', 'desktop', 'other'])],
            'platform' => ['sometimes', 'string', Rule::in(['ios', 'android', 'windows', 'mac', 'linux', 'web'])],
            'device_token' => ['sometimes', 'string', 'max:255'],
            'fcm_token' => ['sometimes', 'string'],
            'is_trusted' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_type.in' => 'Device type must be: mobile, web, tablet, desktop, or other',
            'platform.in' => 'Platform must be: ios, android, windows, mac, linux, or web',
        ];
    }
}