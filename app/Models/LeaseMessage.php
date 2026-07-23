<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'lease_id',
    'sender_user_id',
    'body',
    'channel',
    'read_at',
])]
class LeaseMessage extends Model
{
    use HasUid;

    public const CHANNEL_SHARED = 'shared';

    public const CHANNEL_CONSULTANT_TENANT = 'consultant_tenant';

    public const CHANNEL_CONSULTANT_LANDLORD = 'consultant_landlord';

    public const CHANNELS = [
        self::CHANNEL_SHARED,
        self::CHANNEL_CONSULTANT_TENANT,
        self::CHANNEL_CONSULTANT_LANDLORD,
    ];

    public const CHANNEL_LABELS = [
        self::CHANNEL_SHARED => 'Sözleşme',
        self::CHANNEL_CONSULTANT_TENANT => 'Kiracı',
        self::CHANNEL_CONSULTANT_LANDLORD => 'Ev sahibi',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LeaseMessage $message) {
            if (! $message->channel) {
                $message->channel = self::CHANNEL_SHARED;
            }
        });
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public static function assertCanWrite(User $user, string $channel): void
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw ValidationException::withMessages([
                'channel' => ['Geçersiz mesaj kanalı.'],
            ]);
        }

        if ($user->isStaff()) {
            return;
        }

        $allowed = match ($channel) {
            self::CHANNEL_SHARED => $user->isTenant() || $user->isLandlord(),
            self::CHANNEL_CONSULTANT_TENANT => $user->isTenant(),
            self::CHANNEL_CONSULTANT_LANDLORD => $user->isLandlord(),
            default => false,
        };

        if (! $allowed) {
            abort(response()->json([
                'message' => 'Bu kanala mesaj yazma yetkiniz yok.',
            ], 403));
        }
    }
}
