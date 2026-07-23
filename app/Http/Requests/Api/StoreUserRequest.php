<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
        $isTechnician = $this->input('role') === User::ROLE_TECHNICIAN;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', 'string', Rule::exists('roles', 'slug')],
            'maintenance_category_ids' => [
                Rule::requiredIf($isTechnician),
                'array',
                $isTechnician ? 'min:1' : 'nullable',
            ],
            'maintenance_category_ids.*' => [
                'uuid',
                Rule::exists('maintenance_categories', 'uid'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'maintenance_category_ids.required' => 'Teknisyen için en az bir teknisyen kategorisi seçin.',
            'maintenance_category_ids.min' => 'Teknisyen için en az bir teknisyen kategorisi seçin.',
        ];
    }
}
