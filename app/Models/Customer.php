<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'email',
    'phone',
    'address',
    'notes',
    'status',
    'party_type',
    'interest_type',
    'property_type',
    'budget_min',
    'budget_max',
    'budget_currency',
    'budget_exchange_rate',
    'rooms',
    'preferred_location',
])]
class Customer extends Model
{
    use HasUid;

    public const STATUSES = [
        'new',
        'contacted',
        'interested',
        'closed',
    ];

    public const PARTY_TYPES = [
        'prospect',
        'landlord',
        'tenant',
        'both',
    ];

    public const PARTY_TYPE_LABELS = [
        'prospect' => 'Aday',
        'landlord' => 'Ev sahibi',
        'tenant' => 'Kiracı',
        'both' => 'Ev sahibi + Kiracı',
    ];

    public const INTEREST_TYPES = [
        'buy',
        'rent',
        'both',
    ];

    public const CURRENCIES = ['TRY', 'USD', 'EUR', 'GBP'];

    protected function casts(): array
    {
        return [
            'budget_min' => 'integer',
            'budget_max' => 'integer',
            'budget_exchange_rate' => 'decimal:4',
        ];
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class)->latest('interacted_at');
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type', 'slug');
    }

    public function partyType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class, 'party_type', 'slug');
    }

    public function ownedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'landlord_customer_id');
    }

    public function isLandlord(): bool
    {
        return in_array($this->party_type, ['landlord', 'both'], true);
    }
}
