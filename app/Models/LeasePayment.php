<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lease_id',
    'amount',
    'currency',
    'paid_at',
    'period_label',
    'status',
    'notes',
    'recorded_by',
])]
class LeasePayment extends Model
{
    use HasUid;

    public const STATUSES = ['paid', 'pending', 'overdue'];

    public const STATUS_LABELS = [
        'paid' => 'Ödendi',
        'pending' => 'Bekliyor',
        'overdue' => 'Gecikti',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
