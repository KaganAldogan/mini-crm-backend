<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestImage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TechnicianMaintenanceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $assignedSlugs = $user->maintenanceCategorySlugs();
        $allSlugs = MaintenanceCategory::query()->pluck('slug')->all();

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(MaintenanceRequest::STATUSES)],
            'category' => ['nullable', Rule::in($allSlugs)],
        ]);

        $query = MaintenanceRequest::query()
            ->with(['property', 'tenant:uid,name,email', 'lease', 'technician:uid,name', 'decidedBy:uid,name', 'images', 'categoryType'])
            ->where(function ($inner) use ($assignedSlugs, $user) {
                $inner->whereIn('category', $assignedSlugs ?: ['__none__'])
                    ->orWhere('technician_user_id', $user->id);
            })
            ->latest();

        if (! empty($validated['status'])) {
            if ($validated['status'] === MaintenanceRequest::STATUS_PENDING) {
                // Bekleyen: henüz karar verilmemiş VEYA bana atanmış, randevu girilmemiş
                $query->where(function ($inner) use ($user) {
                    $inner->where('status', MaintenanceRequest::STATUS_PENDING)
                        ->orWhere(function ($awaitingSchedule) use ($user) {
                            $awaitingSchedule
                                ->where('status', MaintenanceRequest::STATUS_APPROVED)
                                ->where('technician_user_id', $user->id)
                                ->whereNull('appointment_at');
                        });
                });
            } elseif ($validated['status'] === MaintenanceRequest::STATUS_APPROVED) {
                // Onaylanan: bana randevu bekleyen atamalar Bekleyen'de kalsın
                $query->where('status', MaintenanceRequest::STATUS_APPROVED)
                    ->where(function ($inner) use ($user) {
                        $inner->whereNull('technician_user_id')
                            ->orWhere('technician_user_id', '!=', $user->id)
                            ->orWhereNotNull('appointment_at');
                    });
            } else {
                $query->where('status', $validated['status']);
            }
        }

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        return MaintenanceRequestResource::collection($query->get());
    }

    public function show(MaintenanceRequest $maintenanceRequest): MaintenanceRequestResource
    {
        $maintenanceRequest->load([
            'property',
            'tenant:uid,name,email',
            'lease',
            'technician:uid,name',
            'decidedBy:uid,name',
            'images',
        ]);

        return new MaintenanceRequestResource($maintenanceRequest);
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
            'technician_note' => $approved && $note !== '' ? $note : null,
            'tenant_note' => ! $approved && $note !== '' ? $note : null,
            'decided_at' => now(),
            'appointment_at' => null,
        ]);

        return new MaintenanceRequestResource($this->loadRelations($maintenanceRequest));
    }

    /**
     * Atanan teknisyen randevu girmeden işi reddeder.
     */
    public function decline(
        Request $request,
        MaintenanceRequest $maintenanceRequest
    ): MaintenanceRequestResource|JsonResponse {
        $validated = $request->validate([
            'tenant_note' => ['required', 'string', 'max:2000'],
        ], [
            'tenant_note.required' => 'Reddetmek için açıklama yazmanız gerekir.',
        ]);

        $note = trim((string) $validated['tenant_note']);
        if ($note === '') {
            return response()->json([
                'message' => 'Reddetmek için açıklama yazmanız gerekir.',
            ], 422);
        }

        if (! $maintenanceRequest->isAssignedTo($request->user())) {
            return response()->json([
                'message' => 'İşi yalnızca atanan teknisyen reddedebilir.',
            ], 403);
        }

        if (
            ! $maintenanceRequest->isApproved()
            || $maintenanceRequest->appointment_at !== null
        ) {
            return response()->json([
                'message' => 'Yalnızca randevu bekleyen atamalar reddedilebilir.',
            ], 422);
        }

        $maintenanceRequest->update([
            'status' => MaintenanceRequest::STATUS_REJECTED,
            'tenant_note' => $note,
            'decided_by_user_id' => $request->user()->id,
            'decided_at' => now(),
            'appointment_at' => null,
        ]);

        return new MaintenanceRequestResource($this->loadRelations($maintenanceRequest));
    }

    /**
     * Atanan teknisyen randevu tarihini girer; talep "işlemde" olur.
     */
    public function schedule(
        Request $request,
        MaintenanceRequest $maintenanceRequest
    ): MaintenanceRequestResource|JsonResponse {
        $validated = $request->validate([
            'appointment_at' => ['required', 'date', 'after:now'],
            'tenant_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'appointment_at.required' => 'Randevu tarihi zorunludur.',
            'appointment_at.after' => 'Randevu tarihi gelecekte olmalıdır.',
        ]);

        if (! $maintenanceRequest->isApproved() && $maintenanceRequest->status !== MaintenanceRequest::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'Yalnızca onaylanmış taleplere randevu atanabilir.',
            ], 422);
        }

        if (! $maintenanceRequest->isAssignedTo($request->user())) {
            return response()->json([
                'message' => 'Randevu tarihini yalnızca atanan teknisyen girebilir.',
            ], 403);
        }

        $tenantNote = isset($validated['tenant_note'])
            ? trim((string) $validated['tenant_note'])
            : '';

        $maintenanceRequest->update([
            'appointment_at' => $validated['appointment_at'],
            'status' => MaintenanceRequest::STATUS_IN_PROGRESS,
            'tenant_note' => $tenantNote !== '' ? $tenantNote : $maintenanceRequest->tenant_note,
        ]);

        return new MaintenanceRequestResource($this->loadRelations($maintenanceRequest));
    }

    /**
     * Atanan teknisyen işi bitirir: ortam fotoğrafı yükler ve tamamlandığını onaylar.
     * Kiracı onayı beklenir.
     */
    public function complete(
        Request $request,
        MaintenanceRequest $maintenanceRequest
    ): MaintenanceRequestResource|JsonResponse {
        $validated = $request->validate([
            'completion_note' => ['nullable', 'string', 'max:2000'],
            'photos' => ['required', 'array', 'min:1', 'max:'.MaintenanceRequestImage::MAX_PER_KIND],
            'photos.*' => [
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:'.MaintenanceRequestImage::MAX_SIZE_KB,
            ],
        ], [
            'photos.required' => 'İş bitiş fotoğrafı zorunludur.',
            'photos.min' => 'En az bir iş bitiş fotoğrafı yükleyin.',
        ]);

        if (! $maintenanceRequest->isInProgress()) {
            return response()->json([
                'message' => 'Yalnızca işlemdeki talepler tamamlanabilir.',
            ], 422);
        }

        if (! $maintenanceRequest->isAssignedTo($request->user())) {
            return response()->json([
                'message' => 'İşi yalnızca atanan teknisyen tamamlayabilir.',
            ], 403);
        }

        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            $photos = $photos ? [$photos] : [];
        }
        $photos = array_values(array_filter($photos));

        if ($photos === []) {
            return response()->json([
                'message' => 'En az bir iş bitiş fotoğrafı yükleyin.',
            ], 422);
        }

        $note = isset($validated['completion_note'])
            ? trim((string) $validated['completion_note'])
            : '';

        $maintenanceRequest->deleteImagesByKind(MaintenanceRequestImage::KIND_COMPLETION);
        $maintenanceRequest->storeImages($photos, MaintenanceRequestImage::KIND_COMPLETION);

        $maintenanceRequest->update([
            'status' => MaintenanceRequest::STATUS_AWAITING_CONFIRMATION,
            'completion_note' => $note !== '' ? $note : null,
            'technician_completed_at' => now(),
            'tenant_confirmed_at' => null,
        ]);

        return new MaintenanceRequestResource($this->loadRelations($maintenanceRequest));
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $assignedSlugs = $user->maintenanceCategorySlugs();
        $base = MaintenanceRequest::query()
            ->where(function ($inner) use ($assignedSlugs, $user) {
                $inner->whereIn('category', $assignedSlugs ?: ['__none__'])
                    ->orWhere('technician_user_id', $user->id);
            });

        // Bekleyen: karar bekleyen VEYA bana atanmış, randevu girilmemiş (liste filtresiyle aynı)
        $pendingCount = (clone $base)->where(function ($inner) use ($user) {
            $inner->where('status', MaintenanceRequest::STATUS_PENDING)
                ->orWhere(function ($awaitingSchedule) use ($user) {
                    $awaitingSchedule
                        ->where('status', MaintenanceRequest::STATUS_APPROVED)
                        ->where('technician_user_id', $user->id)
                        ->whereNull('appointment_at');
                });
        })->count();

        // Onaylanan: randevu bekleyen kendi atamalarım Bekleyen'de kalsın
        $approvedCount = (clone $base)
            ->where('status', MaintenanceRequest::STATUS_APPROVED)
            ->where(function ($inner) use ($user) {
                $inner->whereNull('technician_user_id')
                    ->orWhere('technician_user_id', '!=', $user->id)
                    ->orWhereNotNull('appointment_at');
            })
            ->count();

        return response()->json([
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'rejected_count' => (clone $base)->where('status', MaintenanceRequest::STATUS_REJECTED)->count(),
            'in_progress_count' => (clone $base)->where('status', MaintenanceRequest::STATUS_IN_PROGRESS)->count(),
            'awaiting_confirmation_count' => (clone $base)
                ->where('status', MaintenanceRequest::STATUS_AWAITING_CONFIRMATION)
                ->count(),
        ]);
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
        ]);

        return $maintenanceRequest;
    }
}
