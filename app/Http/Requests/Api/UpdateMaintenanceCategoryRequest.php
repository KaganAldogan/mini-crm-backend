<?php

namespace App\Http\Requests\Api;

use App\Support\TurkishText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateMaintenanceCategoryRequest extends FormRequest
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
        $category = $this->route('maintenance_category');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('maintenance_categories', 'slug')->ignore($category),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
