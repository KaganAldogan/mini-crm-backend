<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\Lease;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TenantMaintenanceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = MaintenanceRequest::query()
            ->where('tenant_user_id', $request->user()->id)
            ->with(['property', 'technician:uid,name', 'decidedBy:uid,name', 'lease', 'images', 'categoryType'])
            ->latest()
            ->get();

        return MaintenanceRequestResource::collection($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lease_id' => ['nullable', 'uuid', 'exists:leases,uid'],
            'category' => ['required', 'string', Rule::exists('maintenance_categories', 'slug')],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:5000'],
            'location' => ['required', 'string', 'max:180'],
            'urgency' => ['required', Rule::in(MaintenanceRequest::URGENCIES)],
            'photos' => ['nullable', 'array', 'max:'.MaintenanceRequestImage::MAX_PER_KIND],
            'photos.*' => [
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:'.MaintenanceRequestImage::MAX_SIZE_KB,
            ],
        ]);

        $lease = null;
        $leaseId = $validated['lease_id'] ?? null;
        if (is_string($leaseId) && $leaseId !== '') {
            $lease = Lease::query()
                ->where('uid', $leaseId)
                ->where('tenant_user_id', $request->user()->id)
                ->first();

            if (! $lease) {
                return response()->json([
                    'message' => 'Sözleşme bulunamadı veya size ait değil.',
                ], 422);
            }
        } else {
            $lease = Lease::query()
                ->where('tenant_user_id', $request->user()->id)
                ->where('status', 'active')
                ->latest('start_date')
                ->first();
        }

        $item = MaintenanceRequest::query()->create([
            'tenant_user_id' => $request->user()->id,
            'lease_id' => $lease?->id,
            'property_id' => $lease?->property_id,
            'category' => $validated['category'],
            'title' => trim($validated['title']),
            'description' => trim($validated['description']),
            'location' => trim($validated['location']),
            'urgency' => $validated['urgency'],
            'status' => MaintenanceRequest::STATUS_PENDING,
        ]);

        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            $photos = $photos ? [$photos] : [];
        }
        $photos = array_values(array_filter($photos));

        if ($photos !== []) {
            $item->storeImages($photos);
        }

        $item->load(['property', 'technician:uid,name', 'lease', 'images']);

        return (new MaintenanceRequestResource($item))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, MaintenanceRequest $maintenanceRequest): MaintenanceRequestResource|JsonResponse
    {
        $this->assertOwns($request, $maintenanceRequest);

        $maintenanceRequest->load([
            'property',
            'technician:uid,name',
            'lease',
            'tenant:uid,name,email',
            'images',
        ]);

        return new MaintenanceRequestResource($maintenanceRequest);
    }

    public function cancel(Request $request, MaintenanceRequest $maintenanceRequest): MaintenanceRequestResource|JsonResponse
    {
        $this->assertOwns($request, $maintenanceRequest);

        if (! $maintenanceRequest->isPending()) {
            return response()->json([
                'message' => 'Yalnızca beklemedeki talepler iptal edilebilir.',
            ], 422);
        }

        $maintenanceRequest->update([
            'status' => MaintenanceRequest::STATUS_CANCELLED,
            'decided_at' => now(),
        ]);

        $maintenanceRequest->load(['property', 'technician:uid,name', 'lease', 'images']);

        return new MaintenanceRequestResource($maintenanceRequest);
    }

    /**
     * Kiracı, teknisyenin iş bitiş onayını ve fotoğraflarını onaylar → tamamlandı.
     */
    public function confirmCompletion(
        Request $request,
        MaintenanceRequest $maintenanceRequest
    ): MaintenanceRequestResource|JsonResponse {
        $this->assertOwns($request, $maintenanceRequest);

        if (! $maintenanceRequest->isAwaitingConfirmation()) {
            return response()->json([
                'message' => 'Bu talep için onay bekleyen bir tamamlanma yok.',
            ], 422);
        }

        $maintenanceRequest->update([
            'status' => MaintenanceRequest::STATUS_COMPLETED,
            'tenant_confirmed_at' => now(),
        ]);

        $maintenanceRequest->load(['property', 'technician:uid,name', 'lease', 'images']);

        return new MaintenanceRequestResource($maintenanceRequest);
    }

    /**
     * Kiracı işin bitmediğini bildirir; talep tekrar işlemde olur.
     */
    public function disputeCompletion(
        Request $request,
        MaintenanceRequest $maintenanceRequest
    ): MaintenanceRequestResource|JsonResponse {
        $this->assertOwns($request, $maintenanceRequest);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'reason.required' => 'İşin neden bitmediğini yazmanız gerekir.',
        ]);

        if (! $maintenanceRequest->isAwaitingConfirmation()) {
            return response()->json([
                'message' => 'Bu talep için itiraz edilecek bir tamamlanma yok.',
            ], 422);
        }

        $reason = trim($validated['reason']);

        $maintenanceRequest->deleteImagesByKind(MaintenanceRequestImage::KIND_COMPLETION);

        $maintenanceRequest->update([
            'status' => MaintenanceRequest::STATUS_IN_PROGRESS,
            'tenant_note' => $reason,
            'completion_note' => null,
            'technician_completed_at' => null,
            'tenant_confirmed_at' => null,
        ]);

        $maintenanceRequest->load(['property', 'technician:uid,name', 'lease', 'images']);

        return new MaintenanceRequestResource($maintenanceRequest);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'categories' => MaintenanceCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn (MaintenanceCategory $category) => [
                    'slug' => $category->slug,
                    'name' => $category->name,
                ])
                ->values(),
            'statuses' => collect(MaintenanceRequest::STATUSES)
                ->map(fn (string $slug) => [
                    'slug' => $slug,
                    'name' => MaintenanceRequest::STATUS_LABELS[$slug] ?? $slug,
                ])
                ->values(),
            'urgencies' => collect(MaintenanceRequest::URGENCIES)
                ->map(fn (string $slug) => [
                    'slug' => $slug,
                    'name' => MaintenanceRequest::URGENCY_LABELS[$slug] ?? $slug,
                ])
                ->values(),
            'max_photos' => MaintenanceRequestImage::MAX_PER_KIND,
            'max_photo_size_kb' => MaintenanceRequestImage::MAX_SIZE_KB,
        ]);
    }

    private function assertOwns(Request $request, MaintenanceRequest $item): void
    {
        abort_unless(
            (string) $item->tenant_user_id === (string) $request->user()->id,
            403,
            'Bu talebe erişim yetkiniz yok.'
        );
    }
}
