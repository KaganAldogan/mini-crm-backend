<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMaintenanceCategoryRequest;
use App\Http\Requests\Api\UpdateMaintenanceCategoryRequest;
use App\Http\Resources\MaintenanceCategoryResource;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MaintenanceCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $types = MaintenanceCategory::query()
            ->withCount('requests')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return MaintenanceCategoryResource::collection($types);
    }

    public function store(StoreMaintenanceCategoryRequest $request): JsonResponse
    {
        $type = MaintenanceCategory::query()->create([
            ...$request->validated(),
            'sort_order' => $request->validated('sort_order', 0),
        ]);

        return (new MaintenanceCategoryResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateMaintenanceCategoryRequest $request,
        MaintenanceCategory $maintenanceCategory
    ): MaintenanceCategoryResource {
        $oldSlug = $maintenanceCategory->slug;
        $newSlug = $request->validated('slug');

        $maintenanceCategory->update($request->validated());

        if ($oldSlug !== $newSlug) {
            MaintenanceRequest::query()
                ->where('category', $oldSlug)
                ->update(['category' => $newSlug]);
        }

        $maintenanceCategory->loadCount('requests');

        return new MaintenanceCategoryResource($maintenanceCategory);
    }

    public function destroy(MaintenanceCategory $maintenanceCategory): Response|JsonResponse
    {
        if ($maintenanceCategory->requests()->exists()) {
            return response()->json([
                'message' => 'Bu kategoriye bağlı arıza talepleri varken silinemez.',
            ], 422);
        }

        if ($maintenanceCategory->technicians()->exists()) {
            return response()->json([
                'message' => 'Bu kategoriye atanmış teknisyenler varken silinemez.',
            ], 422);
        }

        $maintenanceCategory->delete();

        return response()->noContent();
    }
}
