<?php

namespace App\Http\Requests\Api;

use App\Models\Lease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'uuid', 'exists:properties,uid'],
            'tenant_user_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'uid')->where('role', 'tenant'),
            ],
            'consultant_user_id' => ['nullable', 'uuid', 'exists:users,uid'],
            'landlord_customer_id' => [
                'required',
                'uuid',
                Rule::exists('customers', 'uid')->where(function ($query) {
                    $query->whereIn('party_type', ['landlord', 'both']);
                }),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'rent_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', Rule::in(Lease::CURRENCIES)],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'increase_period' => ['required', Rule::in(Lease::INCREASE_PERIODS)],
            'increase_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', Rule::in(Lease::STATUSES)],
            'managed_by_agency' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'deposit_amount' => ['nullable', 'integer', 'min:1'],
            'deposit_currency' => ['nullable', 'string', Rule::in(Lease::CURRENCIES)],
            'deposit_paid_at' => ['nullable', 'date'],
            'deposit_exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'deposit_current_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
