<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'email',
    'phone',
    'interest_type',
    'reason',
    'status',
    'admin_note',
    'reviewed_by',
    'reviewed_at',
    'customer_id',
])]
class CustomerApplication extends Model
{
    use HasUid;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    public const PORTAL_TYPES = [
        'tenant_portal',
        'landlord_portal',
        'buyer_portal',
        'other',
    ];

    /** @deprecated use PORTAL_TYPES */
    public const INTEREST_TYPES = self::PORTAL_TYPES;

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
