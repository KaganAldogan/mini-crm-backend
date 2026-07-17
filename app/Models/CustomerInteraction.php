<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'user_id',
    'type',
    'subject',
    'body',
    'interacted_at',
])]
class CustomerInteraction extends Model
{
    public const TYPES = [
        'call',
        'meeting',
        'visit',
        'message',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'interacted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
