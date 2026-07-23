<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'property_id',
    'tenant_user_id',
    'customer_id',
    'landlord_customer_id',
    'consultant_user_id',
    'start_date',
    'end_date',
    'rent_amount',
    'currency',
    'exchange_rate',
    'due_day',
    'increase_period',
    'increase_rate_percent',
    'next_increase_at',
    'status',
    'managed_by_agency',
    'notes',
    'deposit_amount',
    'deposit_currency',
    'deposit_paid_at',
    'deposit_exchange_rate',
    'deposit_current_rate',
])]
class Lease extends Model
{
    use HasUid;

    public const STATUSES = ['active', 'ended'];

    public const INCREASE_PERIODS = [
        'yearly',
        'semi_annual',
        'quarterly',
        'none',
    ];

    public const INCREASE_PERIOD_LABELS = [
        'yearly' => 'Yıllık',
        'semi_annual' => '6 Aylık',
        'quarterly' => '3 Aylık',
        'none' => 'Artış yok',
    ];

    public const CURRENCIES = ['TRY', 'USD', 'EUR', 'GBP'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'next_increase_at' => 'date',
            'deposit_paid_at' => 'date',
            'rent_amount' => 'integer',
            'deposit_amount' => 'integer',
            'due_day' => 'integer',
            'increase_rate_percent' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'deposit_exchange_rate' => 'decimal:4',
            'deposit_current_rate' => 'decimal:4',
            'managed_by_agency' => 'boolean',
        ];
    }

    public function rentAmountInTry(): ?int
    {
        if ($this->rent_amount === null) {
            return null;
        }

        $currency = strtoupper($this->currency ?: 'TRY');
        if ($currency === 'TRY') {
            return (int) $this->rent_amount;
        }

        if ($this->exchange_rate === null) {
            return null;
        }

        return (int) round($this->rent_amount * (float) $this->exchange_rate);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'landlord_customer_id');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LeasePayment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LeaseDocument::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LeaseMessage::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Mark lease ended and release property when no other active lease remains.
     */
    public function closeAndReleaseProperty(): void
    {
        if ($this->status === 'ended') {
            return;
        }

        $this->update(['status' => 'ended']);

        $property = $this->property;
        if (! $property) {
            return;
        }

        if ($property->status === 'sold') {
            return;
        }

        $otherActive = self::query()
            ->where('property_id', $property->id)
            ->where('status', 'active')
            ->where('uid', '!=', $this->uid)
            ->exists();

        if (! $otherActive) {
            $property->update(['status' => 'active']);
        }
    }

    public function daysUntilEnd(?Carbon $from = null): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        $from ??= now()->startOfDay();

        return (int) $from->diffInDays($this->end_date->copy()->startOfDay(), false);
    }

    public function nextDueDate(?Carbon $from = null): Carbon
    {
        $from ??= now();
        $day = min(max((int) $this->due_day, 1), 28);

        $candidate = $from->copy()->day($day)->startOfDay();

        if ($candidate->lt($from->copy()->startOfDay())) {
            $candidate->addMonth();
            $candidate->day($day);
        }

        return $candidate;
    }

    public function estimatedNextRent(): ?int
    {
        if ($this->increase_period === 'none' || $this->increase_rate_percent === null) {
            return null;
        }

        return (int) round($this->rent_amount * (1 + ((float) $this->increase_rate_percent / 100)));
    }

    /**
     * @return array{
     *   amount: int|null,
     *   currency: string,
     *   paid_at: string|null,
     *   exchange_rate: float|null,
     *   current_rate: float|null,
     *   original_try: int|null,
     *   current_try: int|null,
     *   difference_try: int|null
     * }|null
     */
    public function depositSummary(): ?array
    {
        if ($this->deposit_amount === null) {
            return null;
        }

        $currency = strtoupper($this->deposit_currency ?: 'TRY');
        $isTry = $currency === 'TRY';

        $exchangeRate = $isTry
            ? 1.0
            : ($this->deposit_exchange_rate !== null ? (float) $this->deposit_exchange_rate : null);

        $currentRate = $isTry
            ? 1.0
            : ($this->deposit_current_rate !== null ? (float) $this->deposit_current_rate : null);

        $originalTry = $exchangeRate !== null
            ? (int) round($this->deposit_amount * $exchangeRate)
            : null;

        $currentTry = $currentRate !== null
            ? (int) round($this->deposit_amount * $currentRate)
            : null;

        $difference = ($originalTry !== null && $currentTry !== null)
            ? $currentTry - $originalTry
            : null;

        return [
            'amount' => $this->deposit_amount,
            'currency' => $currency,
            'paid_at' => $this->deposit_paid_at?->toDateString(),
            'exchange_rate' => $isTry ? 1.0 : ($this->deposit_exchange_rate !== null ? (float) $this->deposit_exchange_rate : null),
            'current_rate' => $isTry ? 1.0 : ($this->deposit_current_rate !== null ? (float) $this->deposit_current_rate : null),
            'original_try' => $originalTry,
            'current_try' => $currentTry,
            'difference_try' => $difference,
        ];
    }

    public static function calculateNextIncreaseAt(
        Carbon $startDate,
        string $period,
        ?Carbon $from = null
    ): ?Carbon {
        if ($period === 'none') {
            return null;
        }

        $from ??= now();
        $cursor = $startDate->copy();

        $months = match ($period) {
            'semi_annual' => 6,
            'quarterly' => 3,
            default => 12,
        };

        while ($cursor->lte($from)) {
            $cursor->addMonthsNoOverflow($months);
        }

        return $cursor;
    }
}
