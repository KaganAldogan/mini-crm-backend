<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class StaffMaintenanceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(MaintenanceRequest::STATUSES)],
            'category' => ['nullable', 'string', Rule::exists('maintenance_categories', 'slug')],
        ]);

        $query = MaintenanceRequest::query()
            ->with(['property', 'tenant:uid,name,email', 'lease', 'technician:uid,name', 'decidedBy:uid,name', 'images', 'categoryType'])
            ->latest();

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        return MaintenanceRequestResource::collection($query->get());
    }

    public function show(MaintenanceRequest $maintenanceRequest): MaintenanceRequestResource
    {
        return new MaintenanceRequestResource($this->loadRelations($maintenanceRequest));
    }

    /**
     * Tüm teknisyenler. Talep kategorisiyle eşleşenler üstte gelir.
     */
    public function technicians(Request $request): JsonResponse
    {
        $categorySlugs = MaintenanceCategory::query()->pluck('slug')->all();

        $validated = $request->validate([
            'category' => ['required', Rule::in($categorySlugs)],
        ]);

        $requestCategory = $validated['category'];

        $technicians = User::query()
            ->where('role', User::ROLE_TECHNICIAN)
            ->with([
                'maintenanceCategories' => fn ($q) => $q->orderBy('sort_order')->orderBy('name'),
            ])
            ->orderBy('name')
            ->get(['uid', 'name']);

        $sorted = $technicians
            ->sortByDesc(fn (User $user) => $user->maintenanceCategories
                ->contains(fn ($category) => $category->slug === $requestCategory))
            ->values();

        return response()->json([
            'data' => $sorted->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'matches_request_category' => $user->maintenanceCategories
                    ->contains(fn ($category) => $category->slug === $requestCategory),
                'categories' => $user->maintenanceCategories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])->values(),
            ])->values(),
            'matched_by_category' => $sorted->contains(
                fn (User $user) => $user->maintenanceCategories
                    ->contains(fn ($category) => $category->slug === $requestCategory)
            ),
        ]);
    }

    public function decide(
        Request $request,
        MaintenanceRequest $maintenanceRequest
    ): MaintenanceRequestResource|JsonResponse {
        if (! $request->user()?->hasPermission('maintenance.decide')) {
            return response()->json([
                'message' => 'Bu işlem için yetkiniz yok.',
            ], 403);
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'technician_id' => [
                Rule::requiredIf(fn () => $request->input('decision') === 'approve'),
                'nullable',
                'uuid',
                Rule::exists('users', 'uid')->where('role', User::ROLE_TECHNICIAN),
            ],
            'technician_note' => [
                Rule::requiredIf(fn () => $request->input('decision') === 'reject'),
                'nullable',
                'string',
                'max:2000',
            ],
        ], [
            'technician_id.required' => 'Onaylamak için bir teknisyen seçmeniz gerekir.',
            'technician_note.required' => 'Reddetmek için açıklama yazmanız gerekir.',
        ]);

        if (! $maintenanceRequest->isPending()) {
            return response()->json([
                'message' => 'Bu talep zaten sonuçlandırılmış.',
            ], 422);
        }

        $approved = $validated['decision'] === 'approve';
        $note = isset($validated['technician_note'])
            ? trim((string) $validated['technician_note'])
            : '';

        if (! $approved && $note === '') {
            return response()->json([
                'message' => 'Reddetmek için açıklama yazmanız gerekir.',
            ], 422);
        }

        $technicianId = null;

        if ($approved) {
            $technician = User::query()
                ->where('uid', $validated['technician_id'])
                ->where('role', User::ROLE_TECHNICIAN)
                ->first();

            if (! $technician) {
                return response()->json([
                    'message' => 'Seçilen teknisyen bulunamadı.',
                ], 422);
            }

            $technicianId = $technician->id;
        }

        $maintenanceRequest->update([
            'status' => $approved
                ? MaintenanceRequest::STATUS_APPROVED
                : MaintenanceRequest::STATUS_REJECTED,
            'technician_user_id' => $technicianId,
            'decided_by_user_id' => $request->user()->id,
            // Onay notu yalnızca teknisyene; red gerekçesi kiracıya
            'technician_note' => $approved && $note !== '' ? $note : null,
            'tenant_note' => ! $approved && $note !== '' ? $note : null,
            'decided_at' => now(),
            'appointment_at' => null,
        ]);

        return new MaintenanceRequestResource($this->loadRelations($maintenanceRequest));
    }

    /**
     * Kiracı onaylamazsa yetkili talebi tamamlanmış sayabilir.
     */
    public function forceComplete(
        Request $request,
        MaintenanceRequest $maintenanceRequest
    ): MaintenanceRequestResource|JsonResponse {
        if (! $request->user()?->hasPermission('maintenance.decide')) {
            return response()->json([
                'message' => 'Bu işlem için yetkiniz yok.',
            ], 403);
        }

        if (! $maintenanceRequest->isAwaitingConfirmation()) {
            return response()->json([
                'message' => 'Yalnızca kiracı onayı bekleyen talepler kapatılabilir.',
            ], 422);
        }

        $maintenanceRequest->update([
            'status' => MaintenanceRequest::STATUS_COMPLETED,
        ]);

        return new MaintenanceRequestResource($this->loadRelations($maintenanceRequest));
    }

    private function loadRelations(MaintenanceRequest $maintenanceRequest): MaintenanceRequest
    {
        $maintenanceRequest->load([
            'property',
            'tenant:uid,name,email',
            'lease',
            'technician:uid,name',
            'decidedBy:uid,name',
            'images',
            'categoryType',
        ]);

        return $maintenanceRequest;
    }
}
