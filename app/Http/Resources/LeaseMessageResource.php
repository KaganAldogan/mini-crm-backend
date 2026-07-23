<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\LeaseMessage;

/** @mixin \App\Models\LeaseMessage */
class LeaseMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;

        return [
            'id' => $this->id,
            'lease_id' => $this->lease_id,
            'sender_user_id' => $this->sender_user_id,
            'body' => $this->body,
            'channel' => $this->channel ?? LeaseMessage::CHANNEL_SHARED,
            'channel_label' => LeaseMessage::CHANNEL_LABELS[$this->channel ?? LeaseMessage::CHANNEL_SHARED]
                ?? ($this->channel ?? LeaseMessage::CHANNEL_SHARED),
            'is_mine' => $viewerId !== null && $this->sender_user_id === $viewerId,
            'read_at' => $this->read_at?->toIso8601String(),
            'sender' => $this->whenLoaded('sender', function () {
                if (! $this->sender) {
                    return null;
                }

                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                    'role' => $this->sender->role,
                    'role_label' => match ($this->sender->role) {
                        'tenant' => 'Kiracı',
                        'landlord' => 'Ev sahibi',
                        'admin' => 'Yönetici',
                        'consultant' => 'Danışman',
                        default => 'Personel',
                    },
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
