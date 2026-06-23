<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('user_information');
        
        return [
            'full_name' => ['sometimes', 'array'],
            'full_name.en' => ['sometimes', 'string', 'max:255'],
            'full_name.am' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'string', 'max:20', Rule::unique('user_informations')->ignore($id, 'user_information_id')],
            'national_id_front_image' => ['nullable', 'string', 'max:500'],
            'national_id_back_image' => ['nullable', 'string', 'max:500'],
            'national_id_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'array'],
            'address.en' => ['nullable', 'string', 'max:500'],
            'address.am' => ['nullable', 'string', 'max:500'],
            'device_id' => ['nullable', 'string', 'exists:user_devices,device_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.unique' => 'This phone number is already registered',
            'device_id.exists' => 'Selected device does not exist',
        ];
    }
}