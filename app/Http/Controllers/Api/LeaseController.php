<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLeaseRequest;
use App\Http\Requests\Api\UpdateLeaseRequest;
use App\Http\Resources\LeaseResource;
use App\Models\Customer;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use App\Services\TenantAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LeaseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Lease::query()
            ->with([
                'property.listingType',
                'property.propertyType',
                'tenant',
                'landlord',
                'consultant',
                'payments',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('tenant', function ($tenantQuery) use ($search) {
                    $tenantQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('property', function ($propertyQuery) use ($search) {
                    $propertyQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            });
        }

        return LeaseResource::collection($query->paginate(15));
    }

    public function store(StoreLeaseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenant = User::query()->findOrFail($data['tenant_user_id']);
        $property = Property::query()->findOrFail($data['property_id']);

        if ($property->landlord_customer_id !== $data['landlord_customer_id']) {
            throw ValidationException::withMessages([
                'property_id' => ['Seçilen mülk bu ev sahibine ait değil.'],
            ]);
        }

        $availability = $property->leaseAvailability();
        if (! $availability['available']) {
            throw ValidationException::withMessages([
                'property_id' => [$availability['reason'] ?? 'Bu mülk şu an kiraya verilemez.'],
            ]);
        }

        $startDate = Carbon::parse($data['start_date']);
        $period = $data['increase_period'];

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if (isset($data['deposit_currency'])) {
            $data['deposit_currency'] = strtoupper($data['deposit_currency']);
        }

        $currency = strtoupper($data['currency'] ?? 'TRY');

        $leaseStatus = $data['status'] ?? 'active';

        $lease = Lease::query()->create([
            ...$data,
            'customer_id' => $tenant->customer_id,
            'consultant_user_id' => $data['consultant_user_id'] ?? $request->user()->id,
            'currency' => $currency,
            'exchange_rate' => $currency === 'TRY' ? 1 : ($data['exchange_rate'] ?? null),
            'deposit_currency' => strtoupper($data['deposit_currency'] ?? 'TRY'),
            'status' => $leaseStatus,
            'managed_by_agency' => $data['managed_by_agency'] ?? true,
            'next_increase_at' => Lease::calculateNextIncreaseAt($startDate, $period),
        ]);

        if ($leaseStatus === 'active') {
            $property->update(['status' => 'rented']);
        }

        $lease->load([
            'property.listingType',
            'property.propertyType',
            'tenant',
            'landlord',
            'consultant',
            'payments.recorder',
        ]);

        return (new LeaseResource($lease))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Lease $lease): LeaseResource
    {
        $lease->load([
            'property.listingType',
            'property.propertyType',
            'tenant',
            'landlord',
            'consultant',
            'payments.recorder',
        ]);

        return new LeaseResource($lease);
    }

    public function update(UpdateLeaseRequest $request, Lease $lease): LeaseResource
    {
        $data = $request->validated();

        if (isset($data['tenant_user_id'])) {
            $tenant = User::query()->findOrFail($data['tenant_user_id']);
            $data['customer_id'] = $tenant->customer_id;
        }

        $landlordId = $data['landlord_customer_id'] ?? $lease->landlord_customer_id;
        $propertyId = $data['property_id'] ?? $lease->property_id;

        if ($landlordId && $propertyId) {
            $property = Property::query()->findOrFail($propertyId);
            if ($property->landlord_customer_id !== $landlordId) {
                throw ValidationException::withMessages([
                    'property_id' => ['Seçilen mülk bu ev sahibine ait değil.'],
                ]);
            }

            $availability = $property->leaseAvailability($lease->id);
            if (! $availability['available']) {
                throw ValidationException::withMessages([
                    'property_id' => [$availability['reason'] ?? 'Bu mülk şu an kiraya verilemez.'],
                ]);
            }
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
            if ($data['currency'] === 'TRY') {
                $data['exchange_rate'] = 1;
            }
        }

        if (isset($data['deposit_currency'])) {
            $data['deposit_currency'] = strtoupper($data['deposit_currency']);
        }

        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'])
            : $lease->start_date;
        $period = $data['increase_period'] ?? $lease->increase_period;

        if (isset($data['start_date']) || isset($data['increase_period'])) {
            $data['next_increase_at'] = Lease::calculateNextIncreaseAt(
                $startDate,
                $period
            );
        }

        $lease->update($data);

        $lease->load([
            'property.listingType',
            'property.propertyType',
            'tenant',
            'landlord',
            'consultant',
            'payments.recorder',
        ]);

        return new LeaseResource($lease);
    }

    public function destroy(Lease $lease): JsonResponse
    {
        $lease->delete();

        return response()->json([
            'message' => 'Sözleşme silindi.',
        ]);
    }

    public function tenantUsers(TenantAccountService $tenantAccounts): JsonResponse
    {
        $customers = Customer::query()
            ->whereIn('party_type', ['tenant', 'both'])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        foreach ($customers as $customer) {
            try {
                $tenantAccounts->ensureForCustomer($customer);
            } catch (\Throwable) {
                // E-posta çakışması vb. — listeden düşer, diğerlerini engellemez.
            }
        }

        $users = User::query()
            ->where('role', User::ROLE_TENANT)
            ->orderBy('name')
            ->get(['uid', 'name', 'email', 'customer_id']);

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'customer_id' => $user->customer_id,
            ])->values(),
        ]);
    }
}
