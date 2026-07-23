<?php

namespace App\Http\Resources;

use App\Models\Lease;
use App\Http\Resources\LeasePaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lease */
class LeaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nextDue = $this->nextDueDate();
        $estimatedNextRent = $this->estimatedNextRent();

        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'tenant_user_id' => $this->tenant_user_id,
            'customer_id' => $this->customer_id,
            'landlord_customer_id' => $this->landlord_customer_id,
            'consultant_user_id' => $this->consultant_user_id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days_until_end' => $this->daysUntilEnd(),
            'rent_amount' => $this->rent_amount,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate !== null
                ? (float) $this->exchange_rate
                : null,
            'rent_amount_try' => $this->rentAmountInTry(),
            'due_day' => $this->due_day,
            'increase_period' => $this->increase_period,
            'increase_period_label' => Lease::INCREASE_PERIOD_LABELS[$this->increase_period] ?? $this->increase_period,
            'increase_rate_percent' => $this->increase_rate_percent !== null
                ? (float) $this->increase_rate_percent
                : null,
            'next_increase_at' => $this->next_increase_at?->toDateString(),
            'next_due_date' => $nextDue->toDateString(),
            'estimated_next_rent' => $estimatedNextRent,
            'status' => $this->status,
            'managed_by_agency' => (bool) $this->managed_by_agency,
            'notes' => $this->notes,
            'deposit_amount' => $this->deposit_amount,
            'deposit_currency' => $this->deposit_currency,
            'deposit_paid_at' => $this->deposit_paid_at?->toDateString(),
            'deposit_exchange_rate' => $this->deposit_exchange_rate !== null
                ? (float) $this->deposit_exchange_rate
                : null,
            'deposit_current_rate' => $this->deposit_current_rate !== null
                ? (float) $this->deposit_current_rate
                : null,
            'deposit' => $this->depositSummary(),
            'payments' => $this->whenLoaded('payments', function () {
                return LeasePaymentResource::collection($this->payments)->resolve();
            }),
            'property' => $this->whenLoaded('property', function () {
                return [
                    'id' => $this->property->id,
                    'title' => $this->property->title,
                    'location' => $this->property->location,
                    'rooms' => $this->property->rooms,
                    'area_sqm' => $this->property->area_sqm,
                    'property_type' => $this->property->property_type,
                    'property_type_name' => $this->property->propertyType?->name,
                    'listing_type' => $this->property->listing_type,
                    'listing_type_name' => $this->property->listingType?->name,
                    'status' => $this->property->status,
                ];
            }),
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                    'email' => $this->tenant->email,
                ];
            }),
            'landlord' => $this->whenLoaded('landlord', function () {
                if (! $this->landlord) {
                    return null;
                }

                return [
                    'id' => $this->landlord->id,
                    'name' => $this->landlord->name,
                    'phone' => $this->landlord->phone,
                    'email' => $this->landlord->email,
                ];
            }),
            'consultant' => $this->whenLoaded('consultant', function () {
                if (! $this->consultant) {
                    return null;
                }

                return [
                    'id' => $this->consultant->id,
                    'name' => $this->consultant->name,
                    'email' => $this->consultant->email,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
