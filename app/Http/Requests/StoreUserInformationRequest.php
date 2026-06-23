<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'string', 'exists:users,user_id'],
            'device_id' => ['nullable', 'string', 'exists:user_devices,device_id'],
            'full_name' => ['required', 'array'],
            'full_name.en' => ['required', 'string', 'max:255'],
            'full_name.am' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20', 'unique:user_informations'],
            'national_id_front_image' => ['nullable', 'string', 'max:500'],
            'national_id_back_image' => ['nullable', 'string', 'max:500'],
            'national_id_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'array'],
            'address.en' => ['nullable', 'string', 'max:500'],
            'address.am' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user_id = $this->input('user_id');
            $device_id = $this->input('device_id');
            $national_id_front = $this->input('national_id_front_image');
            $national_id_back = $this->input('national_id_back_image');
            $national_id_number = $this->input('national_id_number');
            
           
            if (empty($user_id) && empty($device_id)) {
                $validator->errors()->add(
                    'user_id',
                    'Either user_id or device_id must be provided.'
                );
            }
            
          
            // 1. If front image is provided, back image is required
            if (!empty($national_id_front) && empty($national_id_back)) {
                $validator->errors()->add(
                    'national_id_back_image',
                    'National ID back image is required when front image is provided.'
                );
            }
            
            // 2. If back image is provided, front image is required
            if (empty($national_id_front) && !empty($national_id_back)) {
                $validator->errors()->add(
                    'national_id_front_image',
                    'National ID front image is required when back image is provided.'
                );
            }
            
          
            if (empty($national_id_front) && empty($national_id_back) && empty($national_id_number)) {
                $validator->errors()->add(
                    'national_id_number',
                    'National ID number is required when ID images are provided.'
                );
            }
            
           
        });
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Full name is required',
            'full_name.en.required' => 'Full name in English is required',
            'full_name.en.max' => 'Full name in English cannot exceed 255 characters',
            'full_name.am.max' => 'Full name in Amharic cannot exceed 255 characters',
            'phone_number.required' => 'Phone number is required',
            'phone_number.unique' => 'This phone number is already registered',
            'phone_number.max' => 'Phone number cannot exceed 20 characters',
            'user_id.exists' => 'Selected user does not exist',
            'device_id.exists' => 'Selected device does not exist',
            'national_id_number.max' => 'National ID number cannot exceed 50 characters',
            'address.en.max' => 'Address in English cannot exceed 500 characters',
            'address.am.max' => 'Address in Amharic cannot exceed 500 characters',
        ];
    }

    protected function prepareForValidation(): void
    {
        // If authenticated and no user_id provided, use authenticated user
        if (auth()->check() && !$this->has('user_id')) {
            $this->merge([
                'user_id' => auth()->id(),
            ]);
        }
        
        // If user_id is provided, remove device_id (user takes precedence)
        if ($this->has('user_id') && !empty($this->input('user_id'))) {
            $this->merge([
                'device_id' => null,
            ]);
        }
    }
}