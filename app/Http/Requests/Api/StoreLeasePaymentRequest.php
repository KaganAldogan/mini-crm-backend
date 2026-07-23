<?php

namespace App\Http\Requests\Api;

use App\Models\LeasePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeasePaymentRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'paid_at' => ['nullable', 'date'],
            'period_label' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(LeasePayment::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
