<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLeaseMessageRequest;
use App\Http\Resources\LeaseDocumentResource;
use App\Http\Resources\LeaseMessageResource;
use App\Http\Resources\LeaseResource;
use App\Models\Lease;
use App\Models\LeaseDocument;
use App\Models\LeaseMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantPortalController extends Controller
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

    public function leases(Request $request): AnonymousResourceCollection
    {
        $leases = Lease::query()
            ->where('tenant_user_id', $request->user()->id)
            ->with([
                'property.listingType',
                'property.propertyType',
                'landlord',
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
            'landlord',
            'consultant',
            'payments.recorder',
        ]);

        return new LeaseResource($lease);
    }

    /** @deprecated Prefer leases() — en güncel aktif sözleşmeyi döner */
    public function lease(Request $request): JsonResponse
    {
        $lease = $this->primaryLeaseFor($request);

        if (! $lease) {
            return response()->json([
                'lease' => null,
                'message' => 'Kira sözleşmeniz bulunmuyor.',
            ]);
        }

        $lease->load([
            'property.listingType',
            'property.propertyType',
            'landlord',
            'consultant',
            'payments',
        ]);

        return response()->json([
            'lease' => (new LeaseResource($lease))->resolve(),
        ]);
    }

    public function documents(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $leaseIds = $this->tenantLeaseIds($request);

        if ($leaseIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'message' => 'Kira sözleşmeniz bulunmuyor.',
            ]);
        }

        $documents = LeaseDocument::query()
            ->whereIn('lease_id', $leaseIds)
            ->with('uploader')
            ->latest()
            ->get();

        return LeaseDocumentResource::collection($documents);
    }

    public function documentsForLease(
        Request $request,
        Lease $lease
    ): AnonymousResourceCollection|JsonResponse {
        $this->assertOwnsLease($request, $lease);

        $documents = $lease->documents()
            ->with('uploader')
            ->latest()
            ->get();

        return LeaseDocumentResource::collection($documents);
    }

    public function downloadDocument(
        Request $request,
        LeaseDocument $document
    ): StreamedResponse|JsonResponse {
        $leaseIds = $this->tenantLeaseIds($request);

        if (! $leaseIds->contains($document->lease_id)) {
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

    public function downloadDocumentForLease(
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

    public function messages(Request $request): AnonymousResourceCollection|JsonResponse
    {
        return $this->listMessages($request, LeaseMessage::CHANNEL_SHARED);
    }

    public function storeMessage(StoreLeaseMessageRequest $request): JsonResponse
    {
        return $this->createMessage($request, LeaseMessage::CHANNEL_SHARED);
    }

    public function markMessagesRead(Request $request): JsonResponse
    {
        return $this->markChannelRead($request, LeaseMessage::CHANNEL_SHARED);
    }

    public function consultantMessages(Request $request): AnonymousResourceCollection|JsonResponse
    {
        return $this->listMessages($request, LeaseMessage::CHANNEL_CONSULTANT_TENANT);
    }

    public function storeConsultantMessage(StoreLeaseMessageRequest $request): JsonResponse
    {
        return $this->createMessage($request, LeaseMessage::CHANNEL_CONSULTANT_TENANT);
    }

    public function markConsultantMessagesRead(Request $request): JsonResponse
    {
        return $this->markChannelRead($request, LeaseMessage::CHANNEL_CONSULTANT_TENANT);
    }

    private function listMessages(
        Request $request,
        string $channel
    ): AnonymousResourceCollection|JsonResponse {
        $lease = $this->primaryLeaseFor($request);

        if (! $lease) {
            return response()->json([
                'data' => [],
                'message' => 'Kira sözleşmeniz bulunmuyor.',
            ]);
        }

        $messages = $lease->messages()
            ->where('channel', $channel)
            ->with('sender:uid,name,role')
            ->orderBy('uid')
            ->get();

        return LeaseMessageResource::collection($messages);
    }

    private function createMessage(StoreLeaseMessageRequest $request, string $channel): JsonResponse
    {
        $lease = $this->primaryLeaseFor($request);

        if (! $lease) {
            return response()->json([
                'message' => 'Kira sözleşmeniz bulunmuyor.',
            ], 404);
        }

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

    private function markChannelRead(Request $request, string $channel): JsonResponse
    {
        $lease = $this->primaryLeaseFor($request);

        if (! $lease) {
            return response()->json([
                'message' => 'Kira sözleşmeniz bulunmuyor.',
                'updated' => 0,
            ]);
        }

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

    private function assertOwnsLease(Request $request, Lease $lease): void
    {
        abort_unless(
            (string) $lease->tenant_user_id === (string) $request->user()->id,
            403,
            'Bu sözleşmeye erişim yetkiniz yok.'
        );
    }

    /** @return \Illuminate\Support\Collection<int, string|int> */
    private function tenantLeaseIds(Request $request)
    {
        return Lease::query()
            ->where('tenant_user_id', $request->user()->id)
            ->pluck('uid');
    }

    private function primaryLeaseFor(Request $request): ?Lease
    {
        return Lease::query()
            ->where('tenant_user_id', $request->user()->id)
            ->where('status', 'active')
            ->latest('start_date')
            ->first()
            ?? Lease::query()
                ->where('tenant_user_id', $request->user()->id)
                ->latest('start_date')
                ->first();
    }
}
