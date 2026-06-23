<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentAccountRequest extends FormRequest
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
        $accountId = $this->route('id');
        
        return [
            // Account information
            'account_type' => ['nullable', 'string', 'max:25', Rule::in(['bank', 'mobile_banking'])],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'account_identifier' => ['nullable', 'string', 'max:255', Rule::unique('payment_accounts', 'account_identifier')->ignore($accountId, 'payment_account_id')],
            'provider' => ['nullable', 'string', 'max:100'],
            
 
            'last_four' => ['nullable', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
            'expiry_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'expiry_year' => ['nullable', 'integer', 'min:' . date('Y'), 'max:' . (date('Y') + 20)],
            
            // Additional metadata
            'meta' => ['nullable', 'array'],
            
            // Status
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            
            // Admin only fields
            'user_id' => ['nullable', 'string', 'uuid', 'exists:users,user_id'],
            'force_delete' => ['sometimes', 'boolean'],
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
            'account_type.in' => 'Account type must be one of: credit_card, debit_card, bank_transfer, paypal, crypto',
            'account_type.max' => 'Account type cannot exceed 25 characters',
            
            'owner_name.max' => 'Owner name cannot exceed 255 characters',
            
            'account_identifier.unique' => 'This account identifier is already registered',
            'account_identifier.max' => 'Account identifier cannot exceed 255 characters',
            
            'provider.max' => 'Provider cannot exceed 100 characters',
            
            'last_four.size' => 'Last four digits must be exactly 4 characters',
            'last_four.regex' => 'Last four digits must contain only numbers',
            
            'expiry_month.min' => 'Expiry month must be between 1 and 12',
            'expiry_month.max' => 'Expiry month must be between 1 and 12',
            'expiry_month.integer' => 'Expiry month must be a number',
            
            'expiry_year.min' => 'Expiry year cannot be in the past',
            'expiry_year.max' => 'Expiry year cannot be more than 20 years in the future',
            'expiry_year.integer' => 'Expiry year must be a number',
            
            'meta.array' => 'Meta must be an array',
            
            'is_default.boolean' => 'Default status must be true or false',
            'is_active.boolean' => 'Active status must be true or false',
            
            'user_id.uuid' => 'User ID must be a valid UUID',
            'user_id.exists' => 'Selected user does not exist',
            
            'force_delete.boolean' => 'Force delete must be true or false',
        ];
    }
}