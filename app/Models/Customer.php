<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'email',
    'phone',
    'address',
    'notes',
    'status',
    'interest_type',
    'property_type',
    'budget_min',
    'budget_max',
    'rooms',
    'preferred_location',
])]
class Customer extends Model
{
    public const STATUSES = [
        'new',
        'contacted',
        'interested',
        'closed',
    ];

    public const INTEREST_TYPES = [
        'buy',
        'rent',
        'both',
    ];

    public const PROPERTY_TYPES = [
        'apartment',
        'house',
        'land',
        'office',
        'shop',
    ];

    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class)->latest('interacted_at');
    }
}
