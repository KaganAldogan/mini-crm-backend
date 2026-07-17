<?php

namespace App\Http\Requests\Api;

use App\Models\CustomerInteraction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerInteractionRequest extends FormRequest
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
            'type' => ['required', Rule::in(CustomerInteraction::TYPES)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'interacted_at' => ['required', 'date'],
        ];
    }
}
