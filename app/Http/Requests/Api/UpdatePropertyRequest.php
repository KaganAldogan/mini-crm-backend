<?php

namespace App\Http\Requests\Api;

use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'listing_type' => ['required', 'string', 'max:100', Rule::exists('listing_types', 'slug')],
            'property_type' => ['required', 'string', 'max:100', Rule::exists('property_types', 'slug')],
            'price' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', Rule::in(Property::CURRENCIES)],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'rooms' => ['nullable', 'string', 'max:50'],
            'area_sqm' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(Property::STATUSES)],
            'landlord_customer_id' => [
                'nullable',
                'uuid',
                Rule::exists('customers', 'uid')->where(function ($query) {
                    $query->whereIn('party_type', ['landlord', 'both']);
                }),
            ],
        ];
    }
}
