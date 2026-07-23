<?php

namespace App\Models;

use App\Models\Concerns\HasUid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'customer_id',
    'lease_end_reminder_enabled',
    'lease_end_reminder_days',
    'lease_end_reminder_email',
])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_CONSULTANT = 'consultant';

    public const ROLE_TENANT = 'tenant';

    public const ROLE_LANDLORD = 'landlord';

    public const ROLE_TECHNICIAN = 'technician';

    public const LEASE_END_REMINDER_DAYS = [7, 14, 30, 60];

    /** @deprecated Use Role model; kept for seeders and legacy checks */
    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_CONSULTANT,
        self::ROLE_TENANT,
        self::ROLE_LANDLORD,
        self::ROLE_TECHNICIAN,
    ];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUid, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'lease_end_reminder_enabled' => 'boolean',
            'lease_end_reminder_days' => 'integer',
            'lease_end_reminder_email' => 'boolean',
        ];
    }

    /**
     * @return array{
     *   lease_end_reminder_enabled: bool,
     *   lease_end_reminder_days: int,
     *   lease_end_reminder_email: bool
     * }
     */
    public function reminderPreferences(): array
    {
        return [
            'lease_end_reminder_enabled' => (bool) ($this->lease_end_reminder_enabled ?? true),
            'lease_end_reminder_days' => (int) ($this->lease_end_reminder_days ?? 30),
            'lease_end_reminder_email' => (bool) ($this->lease_end_reminder_email ?? false),
        ];
    }

    public function wantsLeaseEndReminder(): bool
    {
        return $this->reminderPreferences()['lease_end_reminder_enabled'];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->two_factor_secret)
            && $this->two_factor_confirmed_at !== null;
    }

    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'slug');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function leasesAsTenant(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_user_id');
    }

    public function maintenanceCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            MaintenanceCategory::class,
            'maintenance_category_user',
            'user_id',
            'maintenance_category_id'
        )->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return list<string>
     */
    public function maintenanceCategorySlugs(): array
    {
        return $this->maintenanceCategories()->pluck('slug')->all();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTenant(): bool
    {
        return $this->role === self::ROLE_TENANT;
    }

    public function isLandlord(): bool
    {
        return $this->role === self::ROLE_LANDLORD;
    }

    public function isTechnician(): bool
    {
        return $this->role === self::ROLE_TECHNICIAN;
    }

    public function isPortalUser(): bool
    {
        return $this->isTenant() || $this->isLandlord() || $this->isTechnician();
    }

    public function isStaff(): bool
    {
        return ! $this->isPortalUser();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissionSlugs(), true);
    }

    /**
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        if ($this->isAdmin()) {
            return Permission::query()->pluck('slug')->all();
        }

        return $this->roleModel()
            ->with('permissions')
            ->first()
            ?->permissions
            ->pluck('slug')
            ->all() ?? [];
    }
}
