<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
        $roleId = $this->route('role_id');
        
        return [ 
            'name' => ['sometimes', 'array'],
            'name.en' => ['sometimes', 'string', 'max:100'],
            'name.am' => ['nullable', 'string', 'max:100'],
             
            'description' => ['sometimes', 'array'],
            'description.en' => ['sometimes', 'string', 'max:255'],
            'description.am' => ['nullable', 'string', 'max:255'],
             
            'slug' => ['sometimes', 'string', 'max:50', Rule::unique('roles', 'slug')->ignore($roleId, 'role_id'), 'regex:/^[a-z0-9-_]+$/'],
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
            'name.en.max' => 'Role name in English cannot exceed 100 characters',
            'name.am.max' => 'Role name in Amharic cannot exceed 100 characters',
            
            'description.en.max' => 'Role description in English cannot exceed 255 characters',
            'description.am.max' => 'Role description in Amharic cannot exceed 255 characters',
            
            'slug.unique' => 'This slug is already taken',
            'slug.max' => 'Slug cannot exceed 50 characters',
            'slug.regex' => 'Slug can only contain lowercase letters, numbers, hyphens, and underscores',
        ];
    }
}