<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerInteractionRequest;
use App\Http\Requests\Api\UpdateCustomerInteractionRequest;
use App\Http\Resources\CustomerInteractionResource;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CustomerInteractionController extends Controller
{
    public function index(Customer $customer): AnonymousResourceCollection
    {
        $interactions = $customer->interactions()
            ->with('user:id,name')
            ->get();

        return CustomerInteractionResource::collection($interactions);
    }

    public function store(
        StoreCustomerInteractionRequest $request,
        Customer $customer
    ): JsonResponse {
        $interaction = $customer->interactions()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $interaction->load('user:id,name');

        return (new CustomerInteractionResource($interaction))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCustomerInteractionRequest $request,
        Customer $customer,
        CustomerInteraction $interaction
    ): CustomerInteractionResource {
        abort_unless($interaction->customer_id === $customer->id, 404);

        $interaction->update($request->validated());
        $interaction->load('user:id,name');

        return new CustomerInteractionResource($interaction);
    }

    public function destroy(
        Customer $customer,
        CustomerInteraction $interaction
    ): Response {
        abort_unless($interaction->customer_id === $customer->id, 404);

        $interaction->delete();

        return response()->noContent();
    }
}
