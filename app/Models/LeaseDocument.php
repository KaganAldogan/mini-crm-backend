<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lease_id',
    'uploaded_by',
    'type',
    'title',
    'original_name',
    'path',
    'mime',
    'size',
])]
class LeaseDocument extends Model
{
    use HasUid;

    public const TYPES = [
        'contract',
        'photo',
        'other',
    ];

    public const TYPE_LABELS = [
        'contract' => 'Sözleşme',
        'photo' => 'Fotoğraf',
        'other' => 'Diğer',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
