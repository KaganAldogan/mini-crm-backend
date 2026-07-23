<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerRequest;
use App\Http\Requests\Api\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\TenantAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $partyType = $request->string('party_type')->trim()->toString();
        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);

        $customers = Customer::query()
            ->with(['propertyType', 'partyType'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when(
                $status !== '' && in_array($status, Customer::STATUSES, true),
                fn ($query) => $query->where('status', $status)
            )
            ->when($partyType === 'landlord', function ($query) {
                $query->whereIn('party_type', ['landlord', 'both']);
            })
            ->when(
                $partyType !== '' && $partyType !== 'landlord',
                fn ($query) => $query->where('party_type', $partyType)
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    /**
     * Customer counts for dashboard.
     */
    public function stats(): JsonResponse
    {
        $byStatus = Customer::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];
        foreach (Customer::STATUSES as $status) {
            $counts[$status] = (int) ($byStatus[$status] ?? 0);
        }

        return response()->json([
            'total' => array_sum($counts),
            'by_status' => $counts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StoreCustomerRequest $request,
        TenantAccountService $tenantAccounts
    ): JsonResponse {
        $data = $this->normalizeBudgetCurrency($request->safe()->except(['password', 'password_confirmation']));
        $customer = Customer::create($data);
        $this->syncTenantAccount($customer, $tenantAccounts, $request->input('password'));
        $customer->load(['propertyType', 'partyType']);

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): CustomerResource
    {
        $customer->load(['propertyType', 'partyType']);

        return new CustomerResource($customer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        TenantAccountService $tenantAccounts
    ): CustomerResource {
        $data = $this->normalizeBudgetCurrency($request->safe()->except(['password', 'password_confirmation']));
        $customer->update($data);
        $this->syncTenantAccount($customer->fresh(), $tenantAccounts, $request->input('password'));
        $customer->load(['propertyType', 'partyType']);

        return new CustomerResource($customer);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer): Response
    {
        $customer->delete();

        return response()->noContent();
    }

    private function syncTenantAccount(
        Customer $customer,
        TenantAccountService $tenantAccounts,
        ?string $plainPassword = null
    ): void {
        try {
            $tenantAccounts->ensureForCustomer($customer, $plainPassword);
        } catch (RuntimeException $e) {
            $field = str_contains($e->getMessage(), 'şifre') ? 'password' : 'email';
            throw ValidationException::withMessages([
                $field => [$e->getMessage()],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeBudgetCurrency(array $data): array
    {
        $currency = strtoupper($data['budget_currency'] ?? 'TRY');
        $data['budget_currency'] = $currency;

        if ($currency === 'TRY') {
            $data['budget_exchange_rate'] = 1;
        }

        return $data;
    }
}
