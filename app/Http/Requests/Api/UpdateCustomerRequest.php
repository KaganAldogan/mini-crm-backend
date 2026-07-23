<?php

namespace App\Http\Requests\Api;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('phone')) {
            $merge['phone'] = preg_replace('/\D+/', '', (string) $this->input('phone'));
        }

        if (! $this->filled('status')) {
            $merge['status'] = 'new';
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var \App\Models\Customer|null $customer */
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{L}\s.\'\-]+$/u'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer),
            ],
            'phone' => ['required', 'string', 'size:11', 'regex:/^05\d{9}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(Customer::STATUSES)],
            'party_type' => ['required', 'string', 'max:100', Rule::exists('customer_types', 'slug')],
            'interest_type' => ['nullable', Rule::in(Customer::INTEREST_TYPES)],
            'property_type' => ['nullable', 'string', 'max:100', Rule::exists('property_types', 'slug')],
            'budget_min' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'budget_max' => ['nullable', 'integer', 'min:0', 'max:999999999', 'gte:budget_min'],
            'budget_currency' => ['nullable', 'string', Rule::in(Customer::CURRENCIES)],
            'budget_exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'rooms' => ['nullable', 'string', 'max:10', 'regex:/^\d+\+\d+$/'],
            'preferred_location' => ['nullable', 'string', 'max:255'],
            'password' => [
                Rule::requiredIf(fn () => $this->needsNewTenantPassword()),
                'nullable',
                'confirmed',
                Password::min(8),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $partyType = $this->input('party_type');
            if (in_array($partyType, ['tenant', 'both'], true) && blank($this->input('email'))) {
                $validator->errors()->add('email', 'Kiracı için e-posta zorunludur.');
            }
        });
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
            'rooms.regex' => 'Oda tercihi 3+1 gibi olmalıdır.',
            'password.required' => 'Kiracı portal hesabı için şifre zorunludur.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
        ];
    }

    private function needsNewTenantPassword(): bool
    {
        if (! in_array($this->input('party_type'), ['tenant', 'both'], true)) {
            return false;
        }

        /** @var Customer|null $customer */
        $customer = $this->route('customer');
        if (! $customer) {
            return true;
        }

        $email = strtolower(trim((string) $this->input('email')));
        if ($email === '') {
            return false;
        }

        $hasTenantAccount = User::query()
            ->where('role', User::ROLE_TENANT)
            ->where(function ($query) use ($customer, $email) {
                $query->where('customer_id', $customer->id)
                    ->orWhere('email', $email);
            })
            ->exists();

        return ! $hasTenantAccount;
    }
}
