<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'property_id',
    'type',
    'offer_intent',
    'offer_timing',
    'occurred_at',
    'contact_name',
    'contact_phone',
    'notes',
    'amount',
    'currency',
    'exchange_rate',
    'created_by',
])]
class PropertyInterestEvent extends Model
{
    use HasUid;

    public const TYPES = ['view', 'offer'];

    public const TYPE_LABELS = [
        'view' => 'Görüntüleme',
        'offer' => 'Teklif',
    ];

    public const OFFER_INTENTS = ['rent', 'buy'];

    public const OFFER_INTENT_LABELS = [
        'rent' => 'Kira teklifi',
        'buy' => 'Satın alma teklifi',
    ];

    public const OFFER_TIMINGS = ['immediate', 'after_lease'];

    public const OFFER_TIMING_LABELS = [
        'immediate' => 'Hemen',
        'after_lease' => 'Kiracı çıktıktan / sözleşme bitince',
    ];

    public const CURRENCIES = ['TRY', 'USD', 'EUR', 'GBP'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'amount' => 'integer',
            'exchange_rate' => 'decimal:4',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amountInTry(): ?int
    {
        if ($this->type !== 'offer' || $this->amount === null) {
            return null;
        }

        $currency = strtoupper($this->currency ?: 'TRY');
        if ($currency === 'TRY') {
            return (int) $this->amount;
        }

        if ($this->exchange_rate === null) {
            return null;
        }

        return (int) round($this->amount * (float) $this->exchange_rate);
    }

    public function offerSummaryLabel(): ?string
    {
        if ($this->type !== 'offer') {
            return null;
        }

        $parts = [];

        if ($this->offer_intent) {
            $parts[] = self::OFFER_INTENT_LABELS[$this->offer_intent] ?? $this->offer_intent;
        }

        if ($this->offer_timing) {
            $parts[] = self::OFFER_TIMING_LABELS[$this->offer_timing] ?? $this->offer_timing;
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $isOffer = $this->type === 'offer';

        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'type' => $this->type,
            'type_label' => self::TYPE_LABELS[$this->type] ?? $this->type,
            'offer_intent' => $isOffer ? $this->offer_intent : null,
            'offer_intent_label' => $isOffer && $this->offer_intent
                ? (self::OFFER_INTENT_LABELS[$this->offer_intent] ?? $this->offer_intent)
                : null,
            'offer_timing' => $isOffer ? $this->offer_timing : null,
            'offer_timing_label' => $isOffer && $this->offer_timing
                ? (self::OFFER_TIMING_LABELS[$this->offer_timing] ?? $this->offer_timing)
                : null,
            'offer_summary' => $this->offerSummaryLabel(),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'notes' => $this->notes,
            'amount' => $isOffer ? $this->amount : null,
            'currency' => $isOffer
                ? strtoupper($this->currency ?: 'TRY')
                : null,
            'exchange_rate' => $isOffer && $this->exchange_rate !== null
                ? (float) $this->exchange_rate
                : null,
            'amount_try' => $this->amountInTry(),
            'creator' => $this->relationLoaded('creator') && $this->creator
                ? ['id' => $this->creator->id, 'name' => $this->creator->name]
                : null,
            'property' => $this->relationLoaded('property') && $this->property
                ? [
                    'id' => $this->property->id,
                    'title' => $this->property->title,
                    'location' => $this->property->location,
                    'listing_type' => $this->property->listing_type,
                    'status' => $this->property->status,
                ]
                : null,
        ];
    }
}
