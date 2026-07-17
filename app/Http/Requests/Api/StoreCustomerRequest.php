<?php

namespace App\Http\Requests\Api;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Customer::STATUSES)],
            'interest_type' => ['nullable', Rule::in(Customer::INTEREST_TYPES)],
            'property_type' => ['nullable', Rule::in(Customer::PROPERTY_TYPES)],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0', 'gte:budget_min'],
            'rooms' => ['nullable', 'string', 'max:50'],
            'preferred_location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
