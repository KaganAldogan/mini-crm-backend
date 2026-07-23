<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'title',
    'description',
    'listing_type',
    'property_type',
    'price',
    'currency',
    'exchange_rate',
    'location',
    'rooms',
    'area_sqm',
    'status',
    'cover_image',
    'user_id',
    'landlord_customer_id',
])]
class Property extends Model
{
    use HasUid;

    public const STATUSES = ['active', 'under_contract', 'sold', 'rented'];

    public const CURRENCIES = ['TRY', 'USD', 'EUR', 'GBP'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'area_sqm' => 'integer',
            'exchange_rate' => 'decimal:4',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order')->orderBy('uid');
    }

    public function coverImageUrl(): ?string
    {
        if ($this->relationLoaded('images')) {
            $first = $this->images->first();
            if ($first) {
                return $first->url();
            }
        }

        if (! $this->cover_image) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->cover_image);
    }

    public function syncCoverFromImages(): void
    {
        $first = $this->images()->orderBy('sort_order')->orderBy('uid')->first();
        $this->forceFill([
            'cover_image' => $first?->path,
        ])->save();
    }

    public function deleteCoverImage(): void
    {
        $this->images()->each(function (PropertyImage $image) {
            $image->deleteFile();
            $image->delete();
        });

        $this->forceFill(['cover_image' => null])->save();
    }

    public function storeCoverImage(\Illuminate\Http\UploadedFile $file): PropertyImage
    {
        $this->images()->each(function (PropertyImage $image) {
            $image->deleteFile();
            $image->delete();
        });

        return $this->storeImages([$file])[0];
    }

    /**
     * @param  list<\Illuminate\Http\UploadedFile>  $files
     * @return list<PropertyImage>
     */
    public function storeImages(array $files): array
    {
        $currentCount = $this->images()->count();
        $remaining = PropertyImage::MAX_PER_PROPERTY - $currentCount;

        if ($remaining <= 0) {
            return [];
        }

        $files = array_slice(array_values($files), 0, $remaining);
        $nextOrder = (int) ($this->images()->max('sort_order') ?? -1) + 1;
        $created = [];

        foreach ($files as $file) {
            $path = $file->store("properties/{$this->id}", 'public');

            $created[] = $this->images()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'sort_order' => $nextOrder++,
            ]);
        }

        $this->syncCoverFromImages();

        return $created;
    }

    public function deleteImage(PropertyImage $image): void
    {
        if ($image->property_id !== $this->id) {
            return;
        }

        $image->deleteFile();
        $image->delete();
        $this->syncCoverFromImages();
    }

    public function priceInTry(): ?int
    {
        if ($this->price === null) {
            return null;
        }

        $currency = strtoupper($this->currency ?: 'TRY');
        if ($currency === 'TRY') {
            return (int) $this->price;
        }

        if ($this->exchange_rate === null) {
            return null;
        }

        return (int) round($this->price * (float) $this->exchange_rate);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'landlord_customer_id');
    }

    public function listingType(): BelongsTo
    {
        return $this->belongsTo(ListingType::class, 'listing_type', 'slug');
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type', 'slug');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class)
            ->ofMany(['uid' => 'max'], function ($query) {
                $query->where('status', 'active');
            });
    }

    public function interestEvents(): HasMany
    {
        return $this->hasMany(PropertyInterestEvent::class);
    }

    /**
     * @return array{available: bool, reason: string|null}
     */
    public function leaseAvailability(?string $ignoreLeaseId = null): array
    {
        if ($this->status === 'sold') {
            return [
                'available' => false,
                'reason' => 'Mülk satıldı, kiraya verilemez.',
            ];
        }

        $activeLease = $this->relationLoaded('activeLease')
            ? $this->activeLease
            : $this->activeLease()->with('tenant:uid,name')->first();

        if ($activeLease && $activeLease->uid !== $ignoreLeaseId) {
            $tenantName = $activeLease->tenant?->name;

            return [
                'available' => false,
                'reason' => $tenantName
                    ? "Aktif kirada ({$tenantName})"
                    : 'Aktif kira sözleşmesi var.',
            ];
        }

        if ($this->status === 'rented' && ! $activeLease) {
            return [
                'available' => false,
                'reason' => 'Durumu kiralandı olarak işaretli.',
            ];
        }

        return [
            'available' => true,
            'reason' => null,
        ];
    }
}
