<?php

namespace App\Http\Requests\Api;

use App\Support\TurkishText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreMaintenanceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('name')) {
            return;
        }

        $name = TurkishText::titleCase($this->string('name')->toString());
        $merge = ['name' => $name];

        if (! $this->filled('slug')) {
            $merge['slug'] = Str::slug($name, '_');
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:maintenance_categories,slug'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
