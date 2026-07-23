<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'sort_order',
])]
class ListingType extends Model
{
    use HasUid;

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'listing_type', 'slug');
    }
}
