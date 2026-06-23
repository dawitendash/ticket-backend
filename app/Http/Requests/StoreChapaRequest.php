<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

public class StoreChapaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'amount' => 'required|numeric',
            'currency' => 'required|string',
            'email' => 'nullable|email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number' => 'required|string',
            'callback_url' => 'nullable|url',
            'national_id_front_image' => ['nullable', 'string', 'max:500'],
            'national_id_back_image' => ['nullable', 'string', 'max:500'],
            'national_id_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $national_id_front = $this->input('national_id_front_image');
            $national_id_back = $this->input('national_id_back_image');
            $national_id_number = $this->input('national_id_number');

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
        });
    }
}