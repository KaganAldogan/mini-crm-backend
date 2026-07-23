<?php

namespace App\Http\Requests\Api;

use App\Support\TurkishText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateListingTypeRequest extends FormRequest
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
        $listingType = $this->route('listing_type');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('listing_types', 'slug')->ignore($listingType),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
