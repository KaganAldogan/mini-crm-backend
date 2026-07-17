<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIds = Permission::query()
            ->pluck('id', 'slug');

        $admin = Role::query()->updateOrCreate(
            ['slug' => User::ROLE_ADMIN],
            [
                'name' => 'Yönetici',
                'description' => 'Sistem yöneticisi — tüm yetkilere sahiptir',
                'is_system' => true,
            ]
        );
        $admin->permissions()->sync($permissionIds->values()->all());

        $consultantSlugs = [
            'dashboard.view',
            'customers.view',
            'customers.create',
            'customers.update',
            'interactions.view',
            'interactions.create',
            'interactions.update',
            'properties.view',
            'properties.create',
            'properties.update',
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'reports.view',
        ];

        $consultant = Role::query()->updateOrCreate(
            ['slug' => User::ROLE_CONSULTANT],
            [
                'name' => 'Danışman',
                'description' => 'Emlak danışmanı — kendi müşteri ve portföy işlemleri',
                'is_system' => true,
            ]
        );

        $consultant->permissions()->sync(
            collect($consultantSlugs)
                ->map(fn (string $slug) => $permissionIds[$slug])
                ->filter()
                ->values()
                ->all()
        );
    }
}
