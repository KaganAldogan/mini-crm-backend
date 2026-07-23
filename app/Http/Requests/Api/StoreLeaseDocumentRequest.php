<?php

namespace App\Http\Requests\Api;

use App\Models\LeaseDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('type')) {
            $this->merge(['type' => 'other']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,webp',
            ],
            'type' => ['nullable', Rule::in(LeaseDocument::TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Dosya seçin.',
            'file.mimes' => 'Sadece PDF veya görsel (JPG, PNG, WEBP) yüklenebilir.',
            'file.max' => 'Dosya en fazla 10 MB olabilir.',
        ];
    }
}
