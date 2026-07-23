<?php

namespace App\Http\Requests\Api;

use App\Models\LeaseMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaseMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'channel' => ['nullable', 'string', Rule::in(LeaseMessage::CHANNELS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Mesaj boş olamaz.',
            'body.max' => 'Mesaj en fazla 2000 karakter olabilir.',
            'channel.in' => 'Geçersiz mesaj kanalı.',
        ];
    }
}
