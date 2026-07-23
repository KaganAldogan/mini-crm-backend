<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLeaseMessageRequest;
use App\Http\Resources\LeaseDocumentResource;
use App\Http\Resources\LeaseMessageResource;
use App\Http\Resources\LeaseResource;
use App\Http\Resources\PropertyResource;
use App\Models\Lease;
use App\Models\LeaseDocument;
use App\Models\LeaseMessage;
use App\Models\Property;
use App\Models\PropertyInterestEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandlordPortalController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'customer_id' => $user->customer_id,
            'two_factor_enabled' => config('features.two_factor_enabled')
                && $user->hasTwoFactorEnabled(),
            'preferences' => $user->reminderPreferences(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $customerId = $this->customerIdOrAbort($request);

        $propertiesCount = Property::query()
            ->where('landlord_customer_id', $customerId)
            ->count();

        $activeLeases = Lease::query()
            ->where('landlord_customer_id', $customerId)
            ->where('status', 'active')
            ->with(['property:uid,title', 'tenant:uid,name'])
            ->orderBy('due_day')
            ->get();

        $nextDue = $activeLeases
            ->map(fn (Lease $lease) => [
                'lease_id' => $lease->id,
                'property_title' => $lease->property?->title,
                'tenant_name' => $lease->tenant?->name,
                'next_due_date' => $lease->nextDueDate()->toDateString(),
                'rent_amount' => $lease->rent_amount,
                'currency' => $lease->currency,
            ])
            ->sortBy('next_due_date')
            ->values()
            ->take(5);

        $leasesForReminder = $activeLeases
            ->filter(fn (Lease $lease) => $lease->end_date !== null)
            ->map(fn (Lease $lease) => [
                'lease_id' => $lease->id,
                'property_title' => $lease->property?->title,
                'end_date' => $lease->end_date?->toDateString(),
                'days_until_end' => $lease->daysUntilEnd(),
            ])
            ->sortBy('days_until_end')
            ->values();

        return response()->json([
            'properties_count' => $propertiesCount,
            'active_leases_count' => $activeLeases->count(),
            'upcoming_dues' => $nextDue,
            'leases_with_end_date' => $leasesForReminder,
        ]);
    }

    public function interestEvents(Request $request): JsonResponse
    {
        $customerId = $this->customerIdOrAbort($request);

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(PropertyInterestEvent::TYPES)],
            'offer_intent' => ['nullable', Rule::in(PropertyInterestEvent::OFFER_INTENTS)],
        ]);

        $propertyIds = Property::query()
            ->where('landlord_customer_id', $customerId)
            ->pluck('uid');

        $query = PropertyInterestEvent::query()
            ->whereIn('property_id', $propertyIds)
            ->with([
                'creator:uid,name',
                'property:uid,title,location,listing_type,status',
            ])
            ->latest('occurred_at');

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['offer_intent'])) {
            $query->where('offer_intent', $validated['offer_intent']);
        }

        $events = $query->limit(100)->get();

        $viewsTotal = PropertyInterestEvent::query()
            ->whereIn('property_id', $propertyIds)
            ->where('type', 'view')
            ->count();
        $offersTotal = PropertyInterestEvent::query()
            ->whereIn('property_id', $propertyIds)
            ->where('type', 'offer')
            ->count();

        return response()->json([
            'views_count' => $viewsTotal,
            'offers_count' => $offersTotal,
            'data' => $events->map(fn (PropertyInterestEvent $event) => $event->toApiArray())->values(),
        ]);
    }

    public function properties(Request $request): AnonymousResourceCollection
    {
        $customerId = $this->customerIdOrAbort($request);

        $properties = Property::query()
            ->where('landlord_customer_id', $customerId)
            ->with(['listingType', 'propertyType', 'images', 'activeLease.tenant:uid,name,email'])
            ->withCount([
                'interestEvents as views_count' => fn ($q) => $q->where('type', 'view'),
                'interestEvents as offers_count' => fn ($q) => $q->where('type', 'offer'),
            ])
            ->latest()
            ->get();

        return PropertyResource::collection($properties);
    }

    public function showProperty(Request $request, Property $property): PropertyResource|JsonResponse
    {
        $customerId = $this->customerIdOrAbort($request);

        if ((string) $property->landlord_customer_id !== $customerId) {
            return response()->json([
                'message' => 'Bu mülke erişim yetkiniz yok.',
            ], 403);
        }

        $property->load([
            'listingType',
            'propertyType',
            'images',
            'activeLease.tenant:uid,name,email',
            'interestEvents' => fn ($q) => $q->with('creator:uid,name')->latest('occurred_at'),
        ]);
        $property->loadCount([
            'interestEvents as views_count' => fn ($q) => $q->where('type', 'view'),
            'interestEvents as offers_count' => fn ($q) => $q->where('type', 'offer'),
        ]);

        return new PropertyResource($property);
    }

    public function leases(Request $request): AnonymousResourceCollection
    {
        $customerId = $this->customerIdOrAbort($request);

        $leases = Lease::query()
            ->where('landlord_customer_id', $customerId)
            ->with([
                'property.listingType',
                'property.propertyType',
                'tenant',
                'consultant',
                'payments',
            ])
            ->latest('start_date')
            ->get();

        return LeaseResource::collection($leases);
    }

    public function showLease(Request $request, Lease $lease): LeaseResource|JsonResponse
    {
        $this->assertOwnsLease($request, $lease);

        $lease->load([
            'property.listingType',
            'property.propertyType',
            'tenant',
            'consultant',
            'payments.recorder',
        ]);

        return new LeaseResource($lease);
    }

    public function documents(Request $request, Lease $lease): AnonymousResourceCollection|JsonResponse
    {
        $this->assertOwnsLease($request, $lease);

        $documents = $lease->documents()
            ->with('uploader')
            ->latest()
            ->get();

        return LeaseDocumentResource::collection($documents);
    }

    public function downloadDocument(
        Request $request,
        Lease $lease,
        LeaseDocument $document
    ): StreamedResponse|JsonResponse {
        $this->assertOwnsLease($request, $lease);

        if ($document->lease_id !== $lease->id) {
            return response()->json(['message' => 'Belge bulunamadı.'], 404);
        }

        if (! Storage::disk('local')->exists($document->path)) {
            return response()->json(['message' => 'Dosya bulunamadı.'], 404);
        }

        return Storage::disk('local')->download(
            $document->path,
            $document->original_name
        );
    }

    public function messages(Request $request, Lease $lease): AnonymousResourceCollection|JsonResponse
    {
        return $this->listMessages($request, $lease, LeaseMessage::CHANNEL_SHARED);
    }

    public function storeMessage(StoreLeaseMessageRequest $request, Lease $lease): JsonResponse
    {
        return $this->createMessage($request, $lease, LeaseMessage::CHANNEL_SHARED);
    }

    public function markMessagesRead(Request $request, Lease $lease): JsonResponse
    {
        return $this->markChannelRead($request, $lease, LeaseMessage::CHANNEL_SHARED);
    }

    public function consultantMessages(Request $request, Lease $lease): AnonymousResourceCollection|JsonResponse
    {
        return $this->listMessages($request, $lease, LeaseMessage::CHANNEL_CONSULTANT_LANDLORD);
    }

    public function storeConsultantMessage(StoreLeaseMessageRequest $request, Lease $lease): JsonResponse
    {
        return $this->createMessage($request, $lease, LeaseMessage::CHANNEL_CONSULTANT_LANDLORD);
    }

    public function markConsultantMessagesRead(Request $request, Lease $lease): JsonResponse
    {
        return $this->markChannelRead($request, $lease, LeaseMessage::CHANNEL_CONSULTANT_LANDLORD);
    }

    private function listMessages(
        Request $request,
        Lease $lease,
        string $channel
    ): AnonymousResourceCollection|JsonResponse {
        $this->assertOwnsLease($request, $lease);

        $messages = $lease->messages()
            ->where('channel', $channel)
            ->with('sender:uid,name,role')
            ->orderBy('uid')
            ->get();

        return LeaseMessageResource::collection($messages);
    }

    private function createMessage(
        StoreLeaseMessageRequest $request,
        Lease $lease,
        string $channel
    ): JsonResponse {
        $this->assertOwnsLease($request, $lease);
        LeaseMessage::assertCanWrite($request->user(), $channel);

        $message = $lease->messages()->create([
            'sender_user_id' => $request->user()->id,
            'body' => trim($request->validated('body')),
            'channel' => $channel,
        ]);

        $message->load('sender:uid,name,role');

        return (new LeaseMessageResource($message))
            ->response()
            ->setStatusCode(201);
    }

    private function markChannelRead(
        Request $request,
        Lease $lease,
        string $channel
    ): JsonResponse {
        $this->assertOwnsLease($request, $lease);

        $updated = LeaseMessage::query()
            ->where('lease_id', $lease->id)
            ->where('channel', $channel)
            ->whereNull('read_at')
            ->where('sender_user_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Mesajlar okundu olarak işaretlendi.',
            'updated' => $updated,
        ]);
    }

    private function customerIdOrAbort(Request $request): string
    {
        $user = $request->user();
        $customerId = $user?->customer_id;

        if (! $customerId && $user?->email) {
            $customer = \App\Models\Customer::query()
                ->where('email', $user->email)
                ->whereIn('party_type', ['landlord', 'both'])
                ->first();

            if ($customer) {
                $user->update(['customer_id' => $customer->id]);
                $customerId = $customer->id;
            }
        }

        if (! $customerId) {
            abort(response()->json([
                'message' => 'Ev sahibi müşteri kaydı bağlı değil. Danışmanınızdan hesabınızı müşteri kartına bağlamasını isteyin.',
            ], 422));
        }

        return (string) $customerId;
    }

    private function assertOwnsLease(Request $request, Lease $lease): void
    {
        $customerId = $this->customerIdOrAbort($request);

        if ((string) $lease->landlord_customer_id !== $customerId) {
            abort(response()->json([
                'message' => 'Bu sözleşmeye erişim yetkiniz yok.',
            ], 403));
        }
    }
}
