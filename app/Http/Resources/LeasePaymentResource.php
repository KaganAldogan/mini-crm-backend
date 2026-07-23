<?php

namespace App\Http\Resources;

use App\Models\LeasePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeasePayment */
class LeasePaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lease_id' => $this->lease_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'paid_at' => $this->paid_at?->toDateString(),
            'period_label' => $this->period_label,
            'status' => $this->status,
            'status_label' => LeasePayment::STATUS_LABELS[$this->status] ?? $this->status,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'property_title' => $this->when(
                $this->relationLoaded('lease') && $this->lease?->relationLoaded('property'),
                fn () => $this->lease?->property?->title
            ),
            'tenant_name' => $this->when(
                $this->relationLoaded('lease') && $this->lease?->relationLoaded('tenant'),
                fn () => $this->lease?->tenant?->name
            ),
            'next_due_date' => $this->when(
                $this->relationLoaded('lease') && $this->lease,
                fn () => $this->lease->nextDueDate()->toDateString()
            ),
            'recorder' => $this->whenLoaded('recorder', function () {
                if (! $this->recorder) {
                    return null;
                }

                return [
                    'id' => $this->recorder->id,
                    'name' => $this->recorder->name,
                ];
            }),
            'lease' => $this->whenLoaded('lease', function () {
                if (! $this->lease) {
                    return null;
                }

                return [
                    'id' => $this->lease->id,
                    'next_due_date' => $this->lease->nextDueDate()->toDateString(),
                    'property' => $this->lease->relationLoaded('property') && $this->lease->property
                        ? [
                            'id' => $this->lease->property->id,
                            'title' => $this->lease->property->title,
                        ]
                        : null,
                    'tenant' => $this->lease->relationLoaded('tenant') && $this->lease->tenant
                        ? [
                            'id' => $this->lease->tenant->id,
                            'name' => $this->lease->tenant->name,
                        ]
                        : null,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
