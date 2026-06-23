<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class UpdateUserRequest extends FormRequest
{
  
    public function authorize(): bool
    {
        $user = $this->route('user_id');   
        
        return auth()->user()->isAdmin() || auth()->id() === $user;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user_id');
        
        return [
            // User credentials
            'user_name' => ['sometimes', 'string', 'max:255', Rule::unique('users', 'user_name')->ignore($userId, 'user_id'), 'regex:/^[a-zA-Z0-9_]+$/'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId, 'user_id')],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['nullable', 'string', 'min:8', 'required_with:password'],
            
            // Profile information
            'profile_name' => ['sometimes', 'array'],
            'profile_name.en' => ['sometimes', 'string', 'max:255'],
            'profile_name.am' => ['nullable', 'string', 'max:255'],
            
            'phone_number' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone_number')->ignore($userId, 'user_id'), 'regex:/^[0-9+\-\s]+$/'],
            
            // Role and permissions
            'role_id' => ['nullable', 'string', 'uuid', 'exists:roles,role_id'],
            'gate_number' => ['nullable', 'integer', 'min:0', 'max:999'],
            
            // Status and settings
            'status' => ['sometimes', 'integer', 'in:0,1,2,3'],
            'lang' => ['sometimes', 'string', 'max:5', 'in:en,am'],
            
            // Optional
            'profile_picture_url' => ['nullable', 'string', 'url', 'max:500'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'], // 2MB
            
            // Admin only fields
            'force_delete' => ['sometimes', 'boolean'],
            'login_attempts_reset' => ['sometimes', 'boolean'],
            'unlock_user' => ['sometimes', 'boolean'],
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
            'user_name.unique' => 'This username is already taken',
            'user_name.max' => 'Username cannot exceed 255 characters',
            'user_name.regex' => 'Username can only contain letters, numbers, and underscores',
            
            'email.email' => 'Email must be a valid email address',
            'email.unique' => 'This email is already registered',
            'email.max' => 'Email cannot exceed 255 characters',
            
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            
            'password_confirmation.required_with' => 'Password confirmation is required when changing password',
            'password_confirmation.min' => 'Password confirmation must be at least 8 characters',
            
            'profile_name.en.max' => 'Profile name in English cannot exceed 255 characters',
            'profile_name.am.max' => 'Profile name in Amharic cannot exceed 255 characters',
            
            'phone_number.unique' => 'This phone number is already registered',
            'phone_number.max' => 'Phone number cannot exceed 20 characters',
            'phone_number.regex' => 'Phone number can only contain numbers, +, -, and spaces',
            
            'role_id.exists' => 'Selected role does not exist',
            'role_id.uuid' => 'Role ID must be a valid UUID',
            
            'gate_number.integer' => 'Gate number must be a number',
            'gate_number.min' => 'Gate number cannot be negative',
            'gate_number.max' => 'Gate number cannot exceed 999',
            
            'status.in' => 'Status must be 0 (inactive), 1 (active), 2 (suspended), or 3 (banned)',
            'lang.in' => 'Language must be either en (English) or am (Amharic)',
            
            'profile_picture.image' => 'File must be an image',
            'profile_picture.mimes' => 'Image must be of type: jpeg, png, jpg, gif, webp',
            'profile_picture.max' => 'Image size cannot exceed 2MB',
        ];
    }
}