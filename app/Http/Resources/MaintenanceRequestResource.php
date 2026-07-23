<?php

namespace App\Http\Resources;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MaintenanceRequest */
class MaintenanceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $maskForTenant = $this->shouldMaskApprovalFromTenant($viewer);
        $isTenant = $viewer && method_exists($viewer, 'isTenant') && $viewer->isTenant();

        $status = $maskForTenant
            ? MaintenanceRequest::STATUS_PENDING
            : $this->status;

        $statusLabel = $maskForTenant
            ? MaintenanceRequest::STATUS_LABELS[MaintenanceRequest::STATUS_PENDING]
            : $this->statusLabel();

        $photos = $this->when(
            $this->relationLoaded('images'),
            fn () => $this->mapPhotos(
                $this->images->where('kind', MaintenanceRequestImage::KIND_REQUEST)->values()
            )
        );

        $completionPhotos = $this->when(
            $this->relationLoaded('images'),
            fn () => $this->mapPhotos(
                $this->images->where('kind', MaintenanceRequestImage::KIND_COMPLETION)->values()
            )
        );

        return [
            'id' => $this->id,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'urgency' => $this->urgency,
            'urgency_label' => $this->urgencyLabel(),
            'status' => $status,
            'status_label' => $statusLabel,
            // Yetkili → teknisyen iç notu; kiracıya asla gitmez
            'technician_note' => $isTenant ? null : $this->technician_note,
            // Teknisyen/red → kiracı notu
            'tenant_note' => $maskForTenant ? null : $this->tenant_note,
            'completion_note' => $maskForTenant ? null : $this->completion_note,
            'decided_at' => $maskForTenant ? null : $this->decided_at?->toIso8601String(),
            'appointment_at' => $maskForTenant ? null : $this->appointment_at?->toIso8601String(),
            'technician_completed_at' => $maskForTenant
                ? null
                : $this->technician_completed_at?->toIso8601String(),
            'tenant_confirmed_at' => $this->tenant_confirmed_at?->toIso8601String(),
            'can_cancel' => $this->status === MaintenanceRequest::STATUS_PENDING,
            'can_complete' => $this->canComplete($viewer),
            'can_confirm_completion' => $this->canConfirmCompletion($viewer),
            'can_dispute_completion' => $this->canDisputeCompletion($viewer),
            'can_force_complete' => $this->canForceComplete($viewer),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'email' => $this->tenant->email,
            ] : null),
            'technician' => $this->whenLoaded('technician', function () use ($maskForTenant) {
                if ($maskForTenant || ! $this->technician) {
                    return null;
                }

                return [
                    'id' => $this->technician->id,
                    'name' => $this->technician->name,
                ];
            }),
            'decided_by' => $this->whenLoaded('decidedBy', function () use ($maskForTenant) {
                if ($maskForTenant || ! $this->decidedBy) {
                    return null;
                }

                return [
                    'id' => $this->decidedBy->id,
                    'name' => $this->decidedBy->name,
                ];
            }),
            'lease' => $this->whenLoaded('lease', fn () => $this->lease ? [
                'id' => $this->lease->id,
                'status' => $this->lease->status,
            ] : null),
            'property' => $this->whenLoaded('property', function () {
                if (! $this->property) {
                    return null;
                }

                return [
                    'id' => $this->property->id,
                    'title' => $this->property->title,
                    'location' => $this->property->location,
                ];
            }),
            'photos' => $photos,
            'completion_photos' => $completionPhotos,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MaintenanceRequestImage>  $images
     * @return list<array<string, mixed>>
     */
    private function mapPhotos($images): array
    {
        return $images->map(fn (MaintenanceRequestImage $image) => [
            'id' => $image->id,
            'kind' => $image->kind ?: MaintenanceRequestImage::KIND_REQUEST,
            'url' => $image->url(),
            'original_name' => $image->original_name,
            'mime' => $image->mime,
            'size' => $image->size,
            'sort_order' => $image->sort_order,
        ])->values()->all();
    }

    private function canComplete(mixed $viewer): bool
    {
        if (! $viewer || ! method_exists($viewer, 'isTechnician') || ! $viewer->isTechnician()) {
            return false;
        }

        return $this->status === MaintenanceRequest::STATUS_IN_PROGRESS
            && $this->isAssignedTo($viewer);
    }

    private function canConfirmCompletion(mixed $viewer): bool
    {
        if (! $viewer || ! method_exists($viewer, 'isTenant') || ! $viewer->isTenant()) {
            return false;
        }

        return $this->status === MaintenanceRequest::STATUS_AWAITING_CONFIRMATION
            && (string) $this->tenant_user_id === (string) $viewer->id;
    }

    private function canDisputeCompletion(mixed $viewer): bool
    {
        return $this->canConfirmCompletion($viewer);
    }

    private function canForceComplete(mixed $viewer): bool
    {
        if (! $viewer || ! method_exists($viewer, 'hasPermission')) {
            return false;
        }

        if (! $viewer->hasPermission('maintenance.decide')) {
            return false;
        }

        return $this->status === MaintenanceRequest::STATUS_AWAITING_CONFIRMATION;
    }

    /**
     * Kiracı, admin onayını ve atanan ustayı randevu girilene kadar görmez.
     * Reddedilen talepler hemen görünür.
     */
    private function shouldMaskApprovalFromTenant(mixed $viewer): bool
    {
        if (! $viewer || ! method_exists($viewer, 'isTenant') || ! $viewer->isTenant()) {
            return false;
        }

        if ((string) $this->tenant_user_id !== (string) $viewer->id) {
            return false;
        }

        return $this->status === MaintenanceRequest::STATUS_APPROVED
            && blank($this->appointment_at);
    }
}
