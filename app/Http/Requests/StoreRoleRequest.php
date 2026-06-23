<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
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
        return [ 
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:100'],
            'name.am' => ['nullable', 'string', 'max:100'],
             
            'description' => ['required', 'array'],
            'description.en' => ['required', 'string', 'max:255'],
            'description.am' => ['nullable', 'string', 'max:255'],
            
            'slug' => ['required', 'string', 'max:50', 'unique:roles,slug', 'regex:/^[a-z0-9-_]+$/'],
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
            'name.required' => 'Role name is required',
            'name.en.required' => 'Role name in English is required',
            'name.en.max' => 'Role name in English cannot exceed 100 characters',
            'name.am.max' => 'Role name in Amharic cannot exceed 100 characters',
            
            'description.required' => 'Role description is required',
            'description.en.required' => 'Role description in English is required',
            'description.en.max' => 'Role description in English cannot exceed 255 characters',
            'description.am.max' => 'Role description in Amharic cannot exceed 255 characters',
            
            'slug.required' => 'Role slug is required',
            'slug.unique' => 'This slug is already taken',
            'slug.max' => 'Slug cannot exceed 50 characters',
            'slug.regex' => 'Slug can only contain lowercase letters, numbers, hyphens, and underscores',
        ];
    }
}