<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

#[Fillable([
    'tenant_user_id',
    'lease_id',
    'property_id',
    'technician_user_id',
    'decided_by_user_id',
    'category',
    'title',
    'description',
    'location',
    'urgency',
    'status',
    'technician_note',
    'tenant_note',
    'completion_note',
    'decided_at',
    'appointment_at',
    'technician_completed_at',
    'tenant_confirmed_at',
])]
class MaintenanceRequest extends Model
{
    use HasUid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_AWAITING_CONFIRMATION,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Beklemede',
        self::STATUS_APPROVED => 'Onaylandı',
        self::STATUS_REJECTED => 'Reddedildi',
        self::STATUS_IN_PROGRESS => 'İşlemde',
        self::STATUS_AWAITING_CONFIRMATION => 'Kiracı onayı bekleniyor',
        self::STATUS_COMPLETED => 'Tamamlandı',
        self::STATUS_CANCELLED => 'İptal',
    ];

    public const URGENCY_LOW = 'low';

    public const URGENCY_NORMAL = 'normal';

    public const URGENCY_HIGH = 'high';

    public const URGENCY_EMERGENCY = 'emergency';

    public const URGENCIES = [
        self::URGENCY_LOW,
        self::URGENCY_NORMAL,
        self::URGENCY_HIGH,
        self::URGENCY_EMERGENCY,
    ];

    public const URGENCY_LABELS = [
        self::URGENCY_LOW => 'Düşük',
        self::URGENCY_NORMAL => 'Normal',
        self::URGENCY_HIGH => 'Yüksek',
        self::URGENCY_EMERGENCY => 'Acil',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'appointment_at' => 'datetime',
            'technician_completed_at' => 'datetime',
            'tenant_confirmed_at' => 'datetime',
        ];
    }

    public function categoryType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceCategory::class, 'category', 'slug');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_user_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->technician_user_id === $user->id;
    }

    public function images(): HasMany
    {
        return $this->hasMany(MaintenanceRequestImage::class)
            ->orderBy('sort_order');
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<MaintenanceRequestImage>
     */
    public function storeImages(
        array $files,
        string $kind = MaintenanceRequestImage::KIND_REQUEST
    ): array {
        if (! in_array($kind, MaintenanceRequestImage::KINDS, true)) {
            $kind = MaintenanceRequestImage::KIND_REQUEST;
        }

        $currentCount = $this->images()->where('kind', $kind)->count();
        $remaining = MaintenanceRequestImage::MAX_PER_KIND - $currentCount;

        if ($remaining <= 0) {
            return [];
        }

        $files = array_slice(array_values($files), 0, $remaining);
        $nextOrder = (int) ($this->images()->where('kind', $kind)->max('sort_order') ?? -1) + 1;
        $created = [];

        foreach ($files as $file) {
            $path = $file->store("maintenance/{$this->id}", 'public');

            $created[] = $this->images()->create([
                'kind' => $kind,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'sort_order' => $nextOrder++,
            ]);
        }

        return $created;
    }

    public function deleteImagesByKind(string $kind): void
    {
        $images = $this->images()->where('kind', $kind)->get();

        foreach ($images as $image) {
            $image->deleteFile();
            $image->delete();
        }
    }

    public function isAwaitingConfirmation(): bool
    {
        return $this->status === self::STATUS_AWAITING_CONFIRMATION;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function categoryLabel(): string
    {
        if ($this->relationLoaded('categoryType') && $this->categoryType) {
            return $this->categoryType->name;
        }

        return MaintenanceCategory::query()
            ->where('slug', $this->category)
            ->value('name') ?? $this->category;
    }

    public function urgencyLabel(): string
    {
        return self::URGENCY_LABELS[$this->urgency] ?? $this->urgency;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
