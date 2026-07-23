<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePropertyTypeRequest;
use App\Http\Requests\Api\UpdatePropertyTypeRequest;
use App\Http\Resources\PropertyTypeResource;
use App\Models\Customer;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PropertyTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $types = PropertyType::query()
            ->withCount('properties')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return PropertyTypeResource::collection($types);
    }

    public function store(StorePropertyTypeRequest $request): JsonResponse
    {
        $type = PropertyType::query()->create([
            ...$request->validated(),
            'sort_order' => $request->validated('sort_order', 0),
        ]);

        return (new PropertyTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdatePropertyTypeRequest $request,
        PropertyType $propertyType
    ): PropertyTypeResource|JsonResponse {
        $oldSlug = $propertyType->slug;
        $newSlug = $request->validated('slug');

        $propertyType->update($request->validated());

        if ($oldSlug !== $newSlug) {
            Property::query()
                ->where('property_type', $oldSlug)
                ->update(['property_type' => $newSlug]);

            Customer::query()
                ->where('property_type', $oldSlug)
                ->update(['property_type' => $newSlug]);
        }

        $propertyType->loadCount('properties');

        return new PropertyTypeResource($propertyType);
    }

    public function destroy(PropertyType $propertyType): Response|JsonResponse
    {
        if ($propertyType->properties()->exists()) {
            return response()->json([
                'message' => 'Bu gayrimenkul tipine bağlı menkuller varken silinemez.',
            ], 422);
        }

        if ($propertyType->customers()->exists()) {
            return response()->json([
                'message' => 'Bu gayrimenkul tipine bağlı müşteriler varken silinemez.',
            ], 422);
        }

        $propertyType->delete();

        return response()->noContent();
    }
}
