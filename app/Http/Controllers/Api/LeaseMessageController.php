<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLeaseMessageRequest;
use App\Http\Resources\LeaseMessageResource;
use App\Models\Lease;
use App\Models\LeaseMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LeaseMessageController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $leases = Lease::query()
            ->with([
                'tenant:uid,name,email',
                'landlord:uid,name,phone,email',
                'consultant:uid,name,email',
                'property:uid,title,location',
            ])
            ->orderByDesc('uid')
            ->get();

        $data = $leases->flatMap(function (Lease $lease) use ($userId) {
            return collect(LeaseMessage::CHANNELS)->map(function (string $channel) use ($lease, $userId) {
                $lastMessage = $lease->messages()
                    ->where('channel', $channel)
                    ->with('sender:uid,name,role')
                    ->latest('uid')
                    ->first();

                $unreadCount = $lease->messages()
                    ->where('channel', $channel)
                    ->whereNull('read_at')
                    ->where('sender_user_id', '!=', $userId)
                    ->count();

                return [
                    'lease_id' => $lease->id,
                    'channel' => $channel,
                    'channel_label' => LeaseMessage::CHANNEL_LABELS[$channel] ?? $channel,
                    'status' => $lease->status,
                    'tenant' => $lease->tenant ? [
                        'id' => $lease->tenant->id,
                        'name' => $lease->tenant->name,
                        'email' => $lease->tenant->email,
                    ] : null,
                    'landlord' => $lease->landlord ? [
                        'id' => $lease->landlord->id,
                        'name' => $lease->landlord->name,
                        'phone' => $lease->landlord->phone,
                    ] : null,
                    'consultant' => $lease->consultant ? [
                        'id' => $lease->consultant->id,
                        'name' => $lease->consultant->name,
                    ] : null,
                    'property' => $lease->property ? [
                        'id' => $lease->property->id,
                        'title' => $lease->property->title,
                        'location' => $lease->property->location,
                    ] : null,
                    'last_message' => $lastMessage ? [
                        'id' => $lastMessage->id,
                        'body' => $lastMessage->body,
                        'sender_user_id' => $lastMessage->sender_user_id,
                        'sender_name' => $lastMessage->sender?->name,
                        'created_at' => $lastMessage->created_at?->toIso8601String(),
                    ] : null,
                    'unread_count' => $unreadCount,
                    'last_message_at' => $lastMessage?->created_at?->toIso8601String(),
                ];
            });
        })
            ->sortByDesc(fn (array $item) => $item['last_message_at'] ?? '')
            ->values();

        return response()->json(['data' => $data]);
    }

    public function index(Request $request, Lease $lease): AnonymousResourceCollection
    {
        $channel = $this->resolveChannel($request);

        $messages = $lease->messages()
            ->where('channel', $channel)
            ->with('sender:uid,name,role')
            ->orderBy('uid')
            ->get();

        return LeaseMessageResource::collection($messages);
    }

    public function store(StoreLeaseMessageRequest $request, Lease $lease): JsonResponse
    {
        $channel = $request->validated('channel') ?? LeaseMessage::CHANNEL_SHARED;
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

    public function markRead(Request $request, Lease $lease): JsonResponse
    {
        $channel = $this->resolveChannel($request);

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

    private function resolveChannel(Request $request): string
    {
        $validated = $request->validate([
            'channel' => ['nullable', Rule::in(LeaseMessage::CHANNELS)],
        ]);

        return $validated['channel'] ?? LeaseMessage::CHANNEL_SHARED;
    }
}
