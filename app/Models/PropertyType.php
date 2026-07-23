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
class PropertyType extends Model
{
    use HasUid;

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'property_type', 'slug');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'property_type', 'slug');
    }
}
