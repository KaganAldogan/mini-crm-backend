<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'notes' => $this->notes,
            'status' => $this->status,
            'party_type' => $this->party_type,
            'party_type_label' => $this->partyType?->name
                ?? (\App\Models\Customer::PARTY_TYPE_LABELS[$this->party_type] ?? $this->party_type),
            'interest_type' => $this->interest_type,
            'property_type' => $this->property_type,
            'property_type_name' => $this->propertyType?->name,
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'budget_currency' => strtoupper($this->budget_currency ?: 'TRY'),
            'budget_exchange_rate' => $this->budget_exchange_rate !== null
                ? (float) $this->budget_exchange_rate
                : null,
            'rooms' => $this->rooms,
            'preferred_location' => $this->preferred_location,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
