<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class StoreUserRequest extends FormRequest
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
           
            'user_name' => ['required', 'string', 'max:255', 'unique:users,user_name', 'regex:/^[a-zA-Z0-9_]+$/'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'], 
            
            // Profile information
            'profile_name' => ['required', 'array'],
            'profile_name.en' => ['required', 'string', 'max:255'],
            'profile_name.am' => ['nullable', 'string', 'max:255'],
            
            'phone_number' => ['nullable', 'string', 'max:20', 'unique:users,phone_number', 'regex:/^[0-9+\-\s]+$/'],
            
            // Role and permissions
            'role_id' => ['nullable', 'string', 'uuid', 'exists:roles,role_id'],
            'gate_number' => ['nullable', 'integer', 'min:0', 'max:999'],
            
            // Status and settings
            'status' => ['sometimes', 'integer', 'in:0,1,2,3'],
            'lang' => ['sometimes', 'string', 'max:5', 'in:en,am'],
            
            // Optional
            'profile_picture_url' => ['nullable', 'string', 'url', 'max:500'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'], // 2MB
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
            'user_name.required' => 'Username is required',
            'user_name.unique' => 'This username is already taken',
            'user_name.max' => 'Username cannot exceed 255 characters',
            'user_name.regex' => 'Username can only contain letters, numbers, and underscores',
            
            'email.email' => 'Email must be a valid email address',
            'email.unique' => 'This email is already registered',
            'email.max' => 'Email cannot exceed 255 characters',
            
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            
            'password_confirmation.required' => 'Password confirmation is required',
            'password_confirmation.min' => 'Password confirmation must be at least 8 characters',
            
            'profile_name.required' => 'Profile name is required',
            'profile_name.en.required' => 'Profile name in English is required',
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