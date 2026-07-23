<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLeasePaymentRequest;
use App\Http\Requests\Api\UpdateLeasePaymentRequest;
use App\Http\Resources\LeasePaymentResource;
use App\Models\Lease;
use App\Models\LeasePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LeasePaymentController extends Controller
{
    public function indexAll(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(LeasePayment::STATUSES)],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = LeasePayment::query()
            ->with(['lease.property', 'lease.tenant', 'recorder'])
            ->orderByDesc('paid_at')
            ->orderByDesc('uid');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('period_label', 'like', "%{$search}%")
                    ->orWhereHas('lease.tenant', function ($tenantQuery) use ($search) {
                        $tenantQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('lease.property', function ($propertyQuery) use ($search) {
                        $propertyQuery->where('title', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $validated['per_page'] ?? 15;

        return LeasePaymentResource::collection($query->paginate($perPage));
    }

    public function index(Lease $lease): AnonymousResourceCollection
    {
        $payments = $lease->payments()
            ->with('recorder')
            ->orderByDesc('paid_at')
            ->orderByDesc('uid')
            ->get();

        return LeasePaymentResource::collection($payments);
    }

    public function store(StoreLeasePaymentRequest $request, Lease $lease): JsonResponse
    {
        $data = $request->validated();

        $payment = $lease->payments()->create([
            ...$data,
            'currency' => strtoupper($data['currency'] ?? $lease->currency),
            'recorded_by' => $request->user()->id,
        ]);

        $payment->load('recorder');

        return (new LeasePaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateLeasePaymentRequest $request,
        Lease $lease,
        LeasePayment $payment
    ): LeasePaymentResource|JsonResponse {
        if ($payment->lease_id !== $lease->id) {
            return response()->json(['message' => 'Ödeme bu sözleşmeye ait değil.'], 404);
        }

        $data = $request->validated();

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $payment->update($data);
        $payment->load('recorder');

        return new LeasePaymentResource($payment);
    }

    public function destroy(Lease $lease, LeasePayment $payment): JsonResponse
    {
        if ($payment->lease_id !== $lease->id) {
            return response()->json(['message' => 'Ödeme bu sözleşmeye ait değil.'], 404);
        }

        $payment->delete();

        return response()->json(['message' => 'Ödeme silindi.']);
    }
}
