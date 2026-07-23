<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReviewCustomerApplicationRequest;
use App\Http\Requests\Api\StoreCustomerApplicationRequest;
use App\Http\Resources\CustomerApplicationResource;
use App\Mail\CustomerApplicationDecisionMail;
use App\Models\Customer;
use App\Models\CustomerApplication;
use App\Services\LandlordAccountService;
use App\Services\TenantAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class CustomerApplicationController extends Controller
{
    public function __construct(
        private readonly TenantAccountService $tenantAccounts,
        private readonly LandlordAccountService $landlordAccounts,
    ) {}

    public function store(StoreCustomerApplicationRequest $request): JsonResponse
    {
        $pendingExists = CustomerApplication::query()
            ->where('status', 'pending')
            ->where(function ($q) use ($request) {
                $q->where('email', $request->validated('email'))
                    ->orWhere('phone', $request->validated('phone'));
            })
            ->exists();

        if ($pendingExists) {
            return response()->json([
                'message' => 'Bu e-posta veya telefon ile bekleyen bir başvuru zaten var.',
            ], 422);
        }

        $application = CustomerApplication::query()->create([
            ...$request->validated(),
            'status' => 'pending',
        ]);

        return (new CustomerApplicationResource($application))
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(CustomerApplication::STATUSES)],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = CustomerApplication::query()
            ->with('reviewer')
            ->latest();

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return CustomerApplicationResource::collection($query->get());
    }

    public function show(CustomerApplication $customerApplication): CustomerApplicationResource
    {
        $customerApplication->load('reviewer');

        return new CustomerApplicationResource($customerApplication);
    }

    public function approve(
        ReviewCustomerApplicationRequest $request,
        CustomerApplication $customerApplication
    ): CustomerApplicationResource|JsonResponse {
        if (! $customerApplication->isPending()) {
            return response()->json([
                'message' => 'Bu başvuru zaten sonuçlandırılmış.',
            ], 422);
        }

        $plainPassword = null;
        $portalUser = null;
        $interestType = $customerApplication->interest_type;

        try {
            $application = DB::transaction(function () use (
                $request,
                $customerApplication,
                &$plainPassword,
                &$portalUser
            ) {
                $portalLabel = match ($customerApplication->interest_type) {
                    'tenant_portal' => 'Kiracı Portalı',
                    'landlord_portal' => 'Ev Sahibi Portalı',
                    'buyer_portal' => 'Alıcı Portalı',
                    'other' => 'Diğer',
                    default => $customerApplication->interest_type ?? 'Belirtilmedi',
                };

                $notesBlock = "Portal tercihi: {$portalLabel}\n\nBaşvuru: {$customerApplication->reason}";

                $customer = Customer::query()
                    ->where(function ($q) use ($customerApplication) {
                        $q->where('email', $customerApplication->email)
                            ->orWhere('phone', $customerApplication->phone);
                    })
                    ->first();

                $partyType = $this->partyTypeForInterest(
                    $customerApplication->interest_type,
                    $customer?->party_type
                );

                if (! $customer) {
                    $customer = Customer::query()->create([
                        'name' => $customerApplication->name,
                        'email' => $customerApplication->email,
                        'phone' => $customerApplication->phone,
                        'notes' => $notesBlock,
                        'status' => 'new',
                        'party_type' => $partyType,
                    ]);
                } else {
                    $customer->update([
                        'name' => $customerApplication->name,
                        'notes' => trim(($customer->notes ? $customer->notes."\n\n" : '').$notesBlock),
                        'party_type' => $partyType,
                    ]);
                }

                if ($customerApplication->interest_type === 'tenant_portal') {
                    $provision = $this->tenantAccounts->provisionFromApplication(
                        $customerApplication,
                        $customer
                    );
                    $portalUser = $provision['user'];
                    $plainPassword = $provision['plain_password'];
                }

                if ($customerApplication->interest_type === 'landlord_portal') {
                    $provision = $this->landlordAccounts->provisionFromApplication(
                        $customerApplication,
                        $customer
                    );
                    $portalUser = $provision['user'];
                    $plainPassword = $provision['plain_password'];
                }

                $customerApplication->update([
                    'status' => 'approved',
                    'admin_note' => $request->validated('admin_note'),
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'customer_id' => $customer->id,
                ]);

                return $customerApplication->fresh()->load('reviewer');
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        try {
            if ($interestType === 'tenant_portal' && $portalUser) {
                $this->tenantAccounts->sendCredentialsMail(
                    $application,
                    $portalUser,
                    $plainPassword
                );
            } elseif ($interestType === 'landlord_portal' && $portalUser) {
                $this->landlordAccounts->sendCredentialsMail(
                    $application,
                    $portalUser,
                    $plainPassword
                );
            } else {
                Mail::to($application->email)->send(
                    new CustomerApplicationDecisionMail($application)
                );
            }
        } catch (\Throwable) {
            // Mail yapılandırması yoksa onay işlemi yine de tamamlanır.
        }

        return new CustomerApplicationResource($application);
    }

    public function reject(
        ReviewCustomerApplicationRequest $request,
        CustomerApplication $customerApplication
    ): CustomerApplicationResource|JsonResponse {
        if (! $customerApplication->isPending()) {
            return response()->json([
                'message' => 'Bu başvuru zaten sonuçlandırılmış.',
            ], 422);
        }

        $customerApplication->update([
            'status' => 'rejected',
            'admin_note' => $request->validated('admin_note'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $application = $customerApplication->fresh()->load('reviewer');

        try {
            Mail::to($application->email)->send(
                new CustomerApplicationDecisionMail($application)
            );
        } catch (\Throwable) {
            // Mail yapılandırması yoksa red işlemi yine de tamamlanır.
        }

        return new CustomerApplicationResource($application);
    }

    private function partyTypeForInterest(?string $interestType, ?string $currentPartyType): string
    {
        return match ($interestType) {
            'tenant_portal' => match ($currentPartyType) {
                'landlord', 'both' => 'both',
                default => 'tenant',
            },
            'landlord_portal' => match ($currentPartyType) {
                'tenant', 'both' => 'both',
                default => 'landlord',
            },
            default => $currentPartyType ?: 'prospect',
        };
    }
}
