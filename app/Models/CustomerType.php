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
class CustomerType extends Model
{
    use HasUid;

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'party_type', 'slug');
    }
}
