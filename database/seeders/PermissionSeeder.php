<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Dashboard' => [
                ['name' => 'Dashboard Görüntüle', 'slug' => 'dashboard.view'],
            ],
            'Müşteriler' => [
                ['name' => 'Müşteri Görüntüle', 'slug' => 'customers.view'],
                ['name' => 'Müşteri Oluştur', 'slug' => 'customers.create'],
                ['name' => 'Müşteri Düzenle', 'slug' => 'customers.update'],
                ['name' => 'Müşteri Sil', 'slug' => 'customers.delete'],
                ['name' => 'Tüm Müşterileri Gör', 'slug' => 'customers.view_all'],
            ],
            'Görüşmeler' => [
                ['name' => 'Görüşme Görüntüle', 'slug' => 'interactions.view'],
                ['name' => 'Görüşme Oluştur', 'slug' => 'interactions.create'],
                ['name' => 'Görüşme Düzenle', 'slug' => 'interactions.update'],
                ['name' => 'Görüşme Sil', 'slug' => 'interactions.delete'],
            ],
            'Portföy' => [
                ['name' => 'İlan Görüntüle', 'slug' => 'properties.view'],
                ['name' => 'İlan Oluştur', 'slug' => 'properties.create'],
                ['name' => 'İlan Düzenle', 'slug' => 'properties.update'],
                ['name' => 'İlan Sil', 'slug' => 'properties.delete'],
            ],
            'Randevular' => [
                ['name' => 'Randevu Görüntüle', 'slug' => 'appointments.view'],
                ['name' => 'Randevu Oluştur', 'slug' => 'appointments.create'],
                ['name' => 'Randevu Düzenle', 'slug' => 'appointments.update'],
                ['name' => 'Randevu Sil', 'slug' => 'appointments.delete'],
            ],
            'Kullanıcılar' => [
                ['name' => 'Kullanıcı Görüntüle', 'slug' => 'users.view'],
                ['name' => 'Kullanıcı Oluştur', 'slug' => 'users.create'],
                ['name' => 'Kullanıcı Düzenle', 'slug' => 'users.update'],
                ['name' => 'Kullanıcı Sil', 'slug' => 'users.delete'],
            ],
            'Roller' => [
                ['name' => 'Rol Görüntüle', 'slug' => 'roles.view'],
                ['name' => 'Rol Oluştur', 'slug' => 'roles.create'],
                ['name' => 'Rol Düzenle', 'slug' => 'roles.update'],
                ['name' => 'Rol Sil', 'slug' => 'roles.delete'],
            ],
            'Raporlar' => [
                ['name' => 'Rapor Görüntüle', 'slug' => 'reports.view'],
                ['name' => 'Rapor Dışa Aktar', 'slug' => 'reports.export'],
            ],
            'Ayarlar' => [
                ['name' => 'Ayarları Yönet', 'slug' => 'settings.manage'],
            ],
        ];

        $sort = 0;

        foreach ($catalog as $group => $items) {
            foreach ($items as $item) {
                Permission::query()->updateOrCreate(
                    ['slug' => $item['slug']],
                    [
                        'name' => $item['name'],
                        'group' => $group,
                        'sort_order' => $sort++,
                    ]
                );
            }
        }
    }
}
