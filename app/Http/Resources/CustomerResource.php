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
            'interest_type' => $this->interest_type,
            'property_type' => $this->property_type,
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'rooms' => $this->rooms,
            'preferred_location' => $this->preferred_location,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
