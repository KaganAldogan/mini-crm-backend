<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePropertyRequest;
use App\Http\Requests\Api\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'listing_type' => ['nullable', 'string', 'max:100', Rule::exists('listing_types', 'slug')],
            'property_type' => ['nullable', 'string', 'max:100', Rule::exists('property_types', 'slug')],
            'status' => ['nullable', Rule::in(Property::STATUSES)],
            'landlord_customer_id' => ['nullable', 'uuid', 'exists:customers,uid'],
            'rooms' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Property::query()
            ->with([
                'user',
                'listingType',
                'propertyType',
                'landlord',
                'images',
                'activeLease.tenant:uid,name',
            ])
            ->withCount([
                'interestEvents as views_count' => fn ($q) => $q->where('type', 'view'),
                'interestEvents as offers_count' => fn ($q) => $q->where('type', 'offer'),
            ])
            ->latest();

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('rooms', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['listing_type'])) {
            $query->where('listing_type', $validated['listing_type']);
        }

        if (! empty($validated['property_type'])) {
            $query->where('property_type', $validated['property_type']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['landlord_customer_id'])) {
            $query->where('landlord_customer_id', $validated['landlord_customer_id']);
        }

        if (! empty($validated['rooms'])) {
            $query->where('rooms', 'like', '%'.$validated['rooms'].'%');
        }

        $perPage = $validated['per_page'] ?? 10;

        return PropertyResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $currency = strtoupper($data['currency'] ?? 'TRY');

        $property = Property::query()->create([
            ...$data,
            'currency' => $currency,
            'exchange_rate' => $currency === 'TRY' ? 1 : ($data['exchange_rate'] ?? null),
            'status' => $data['status'] ?? 'active',
            'user_id' => $request->user()->id,
        ]);

        $property->load(['user', 'listingType', 'propertyType', 'landlord', 'images']);

        return (new PropertyResource($property))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Property $property): PropertyResource
    {
        $property->load([
            'user',
            'listingType',
            'propertyType',
            'landlord',
            'images',
            'activeLease.tenant:uid,name',
            'interestEvents' => fn ($q) => $q->with('creator:uid,name')->latest('occurred_at'),
        ]);
        $property->loadCount([
            'interestEvents as views_count' => fn ($q) => $q->where('type', 'view'),
            'interestEvents as offers_count' => fn ($q) => $q->where('type', 'offer'),
        ]);

        return new PropertyResource($property);
    }

    public function update(UpdatePropertyRequest $request, Property $property): PropertyResource
    {
        $data = $request->validated();

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
            if ($data['currency'] === 'TRY') {
                $data['exchange_rate'] = 1;
            }
        }

        $property->update($data);
        $property->load(['user', 'listingType', 'propertyType', 'landlord', 'images']);

        return new PropertyResource($property);
    }

    public function destroy(Property $property): Response
    {
        $property->deleteCoverImage();
        $property->delete();

        return response()->noContent();
    }

    public function uploadCover(Request $request, Property $property): PropertyResource
    {
        $request->validate([
            'cover_image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $property->storeCoverImage($request->file('cover_image'));
        $property->load(['user', 'listingType', 'propertyType', 'landlord', 'images']);

        return new PropertyResource($property);
    }

    public function deleteCover(Property $property): PropertyResource
    {
        $property->deleteCoverImage();
        $property->load(['user', 'listingType', 'propertyType', 'landlord', 'images']);

        return new PropertyResource($property);
    }

    public function uploadImages(Request $request, Property $property): PropertyResource|JsonResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.PropertyImage::MAX_PER_PROPERTY],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $currentCount = $property->images()->count();
        $incoming = count($request->file('images', []));

        if ($currentCount + $incoming > PropertyImage::MAX_PER_PROPERTY) {
            $remaining = max(PropertyImage::MAX_PER_PROPERTY - $currentCount, 0);

            return response()->json([
                'message' => $remaining === 0
                    ? 'Bu ilan için en fazla '.PropertyImage::MAX_PER_PROPERTY.' fotoğraf yüklenebilir.'
                    : "En fazla {$remaining} fotoğraf daha ekleyebilirsiniz.",
            ], 422);
        }

        $property->storeImages($request->file('images'));
        $property->load(['user', 'listingType', 'propertyType', 'landlord', 'images']);

        return new PropertyResource($property);
    }

    public function deleteImage(Property $property, PropertyImage $image): PropertyResource|JsonResponse
    {
        if ($image->property_id !== $property->id) {
            return response()->json(['message' => 'Fotoğraf bu ilana ait değil.'], 404);
        }

        $property->deleteImage($image);
        $property->load(['user', 'listingType', 'propertyType', 'landlord', 'images']);

        return new PropertyResource($property);
    }
}
