<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerTypeRequest;
use App\Http\Requests\Api\UpdateCustomerTypeRequest;
use App\Http\Resources\CustomerTypeResource;
use App\Models\Customer;
use App\Models\CustomerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CustomerTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $types = CustomerType::query()
            ->withCount('customers')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CustomerTypeResource::collection($types);
    }

    public function store(StoreCustomerTypeRequest $request): JsonResponse
    {
        $type = CustomerType::query()->create([
            ...$request->validated(),
            'sort_order' => $request->validated('sort_order', 0),
        ]);

        return (new CustomerTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCustomerTypeRequest $request,
        CustomerType $customerType
    ): CustomerTypeResource {
        $oldSlug = $customerType->slug;
        $newSlug = $request->validated('slug');

        $customerType->update($request->validated());

        if ($oldSlug !== $newSlug) {
            Customer::query()
                ->where('party_type', $oldSlug)
                ->update(['party_type' => $newSlug]);
        }

        $customerType->loadCount('customers');

        return new CustomerTypeResource($customerType);
    }

    public function destroy(CustomerType $customerType): Response|JsonResponse
    {
        if ($customerType->customers()->exists()) {
            return response()->json([
                'message' => 'Bu müşteri tipine bağlı müşteriler varken silinemez.',
            ], 422);
        }

        $customerType->delete();

        return response()->noContent();
    }
}
