<?php

namespace App\Http\Resources;

use App\Models\PropertyInterestEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Property */
class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availability = $this->leaseAvailability();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'listing_type' => $this->listing_type,
            'listing_type_name' => $this->listingType?->name,
            'property_type' => $this->property_type,
            'property_type_name' => $this->propertyType?->name,
            'price' => $this->price,
            'currency' => strtoupper($this->currency ?: 'TRY'),
            'exchange_rate' => $this->exchange_rate !== null
                ? (float) $this->exchange_rate
                : null,
            'price_try' => $this->priceInTry(),
            'location' => $this->location,
            'rooms' => $this->rooms,
            'area_sqm' => $this->area_sqm,
            'status' => $this->status,
            'cover_image' => $this->cover_image,
            'cover_image_url' => $this->coverImageUrl(),
            'images' => $this->when(
                $this->relationLoaded('images'),
                fn () => $this->images->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->url(),
                    'original_name' => $image->original_name,
                    'mime' => $image->mime,
                    'size' => $image->size,
                    'sort_order' => $image->sort_order,
                ])->values()->all()
            ),
            'landlord_customer_id' => $this->landlord_customer_id,
            'views_count' => (int) ($this->views_count ?? 0),
            'offers_count' => (int) ($this->offers_count ?? 0),
            'available_for_lease' => $availability['available'],
            'unavailable_reason' => $availability['reason'],
            'interest_events' => $this->whenLoaded('interestEvents', function () {
                return $this->interestEvents->map(
                    fn (PropertyInterestEvent $event) => $event->toApiArray()
                );
            }),
            'active_lease' => $this->whenLoaded('activeLease', function () {
                if (! $this->activeLease) {
                    return null;
                }

                $lease = $this->activeLease;

                return [
                    'id' => $lease->id,
                    'tenant_name' => $lease->tenant?->name,
                    'tenant_email' => $lease->tenant?->email,
                    'status' => $lease->status,
                    'rent_amount' => $lease->rent_amount,
                    'currency' => $lease->currency,
                    'start_date' => $lease->start_date?->toDateString(),
                    'end_date' => $lease->end_date?->toDateString(),
                    'due_day' => $lease->due_day,
                    'next_due_date' => $lease->nextDueDate()->toDateString(),
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
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
