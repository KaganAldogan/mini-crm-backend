<?php

namespace App\Http\Requests\Api;

use App\Models\CustomerApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/\D+/', '', (string) $this->input('phone')),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{L}\s.\'\-]+$/u'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'size:11', 'regex:/^05\d{9}$/'],
            'interest_type' => ['required', Rule::in(CustomerApplication::PORTAL_TYPES)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Ad yalnızca harf içermelidir.',
            'phone.size' => 'Telefon numarası 11 haneli olmalıdır.',
            'phone.regex' => 'Telefon 05 ile başlayan 11 haneli bir numara olmalıdır.',
            'interest_type.required' => 'Portal tercihi zorunludur.',
            'reason.min' => 'Başvuru nedeni en az 10 karakter olmalıdır.',
        ];
    }
}
