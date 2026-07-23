<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'maintenance_request_id',
    'kind',
    'path',
    'original_name',
    'mime',
    'size',
    'sort_order',
])]
class MaintenanceRequestImage extends Model
{
    use HasUid;

    public const KIND_REQUEST = 'request';

    public const KIND_COMPLETION = 'completion';

    public const KINDS = [
        self::KIND_REQUEST,
        self::KIND_COMPLETION,
    ];

    public const MAX_PER_REQUEST = 5;

    public const MAX_PER_KIND = 5;

    public const MAX_SIZE_KB = 5120;

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function deleteFile(): void
    {
        if ($this->path && Storage::disk('public')->exists($this->path)) {
            Storage::disk('public')->delete($this->path);
        }
    }
}
