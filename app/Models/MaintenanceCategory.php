<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'sort_order',
])]
class MaintenanceCategory extends Model
{
    use HasUid;

    public function requests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'category', 'slug');
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'maintenance_category_user',
            'maintenance_category_id',
            'user_id'
        );
    }
}
