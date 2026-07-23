<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreListingTypeRequest;
use App\Http\Requests\Api\UpdateListingTypeRequest;
use App\Http\Resources\ListingTypeResource;
use App\Models\ListingType;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ListingTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $types = ListingType::query()
            ->withCount('properties')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ListingTypeResource::collection($types);
    }

    public function store(StoreListingTypeRequest $request): JsonResponse
    {
        $type = ListingType::query()->create([
            ...$request->validated(),
            'sort_order' => $request->validated('sort_order', 0),
        ]);

        return (new ListingTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateListingTypeRequest $request,
        ListingType $listingType
    ): ListingTypeResource|JsonResponse {
        $oldSlug = $listingType->slug;
        $newSlug = $request->validated('slug');

        $listingType->update($request->validated());

        if ($oldSlug !== $newSlug) {
            Property::query()
                ->where('listing_type', $oldSlug)
                ->update(['listing_type' => $newSlug]);
        }

        $listingType->loadCount('properties');

        return new ListingTypeResource($listingType);
    }

    public function destroy(ListingType $listingType): Response|JsonResponse
    {
        if ($listingType->properties()->exists()) {
            return response()->json([
                'message' => 'Bu ilan türüne bağlı menkuller varken silinemez.',
            ], 422);
        }

        $listingType->delete();

        return response()->noContent();
    }
}
