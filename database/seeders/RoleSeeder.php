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
            ->pluck('uid', 'slug');

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
            'leases.view',
            'leases.create',
            'leases.update',
            'leases.delete',
            'payments.view',
            'payments.manage',
            'documents.view',
            'documents.manage',
            'messages.view',
            'messages.send',
            'interest.view',
            'maintenance.view',
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
                ->map(fn (string $slug) => $permissionIds[$slug] ?? null)
                ->filter()
                ->values()
                ->all()
        );

        $tenant = Role::query()->updateOrCreate(
            ['slug' => User::ROLE_TENANT],
            [
                'name' => 'Kiracı',
                'description' => 'Kiracı — sözleşme, ödeme ve arıza taleplerine erişir',
                'is_system' => true,
            ]
        );

        $tenant->permissions()->sync(
            collect([
                'dashboard.view',
                'leases.view_own',
                'payments.view_own',
                'documents.view',
                'messages.view',
                'messages.send',
                'maintenance.view_own',
                'maintenance.create',
            ])
                ->map(fn (string $slug) => $permissionIds[$slug] ?? null)
                ->filter()
                ->values()
                ->all()
        );

        $landlord = Role::query()->updateOrCreate(
            ['slug' => User::ROLE_LANDLORD],
            [
                'name' => 'Ev sahibi',
                'description' => 'Ev sahibi — yalnızca kendi mülk, sözleşme ve ödeme verilerine erişir',
                'is_system' => true,
            ]
        );

        $landlord->permissions()->sync(
            collect([
                'dashboard.view',
                'leases.view_as_landlord',
                'payments.view_as_landlord',
                'properties.view_own',
                'interest.view_own',
                'documents.view',
                'messages.view',
                'messages.send',
            ])
                ->map(fn (string $slug) => $permissionIds[$slug] ?? null)
                ->filter()
                ->values()
                ->all()
        );

        $technician = Role::query()->updateOrCreate(
            ['slug' => User::ROLE_TECHNICIAN],
            [
                'name' => 'Teknisyen',
                'description' => 'Teknisyen — arıza taleplerini görür, onaylar veya reddeder',
                'is_system' => true,
            ]
        );

        $technician->permissions()->sync(
            collect([
                'dashboard.view',
                'maintenance.view_as_technician',
                'maintenance.decide',
            ])
                ->map(fn (string $slug) => $permissionIds[$slug] ?? null)
                ->filter()
                ->values()
                ->all()
        );
    }
}
