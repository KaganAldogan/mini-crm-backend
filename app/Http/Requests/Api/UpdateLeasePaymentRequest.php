<?php

namespace App\Http\Requests\Api;

use App\Models\LeasePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeasePaymentRequest extends FormRequest
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
            'amount' => ['sometimes', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'paid_at' => ['nullable', 'date'],
            'period_label' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(LeasePayment::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
