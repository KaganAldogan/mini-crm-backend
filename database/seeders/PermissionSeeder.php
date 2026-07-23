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
            // Personel / ofis — kira CRUD
            'Kira Yönetimi (Personel)' => [
                ['name' => 'Sözleşme Görüntüle', 'slug' => 'leases.view'],
                ['name' => 'Sözleşme Oluştur', 'slug' => 'leases.create'],
                ['name' => 'Sözleşme Düzenle', 'slug' => 'leases.update'],
                ['name' => 'Sözleşme Sil', 'slug' => 'leases.delete'],
                ['name' => 'Ödeme Görüntüle', 'slug' => 'payments.view'],
                ['name' => 'Ödeme Yönet', 'slug' => 'payments.manage'],
                ['name' => 'Belge Yönet', 'slug' => 'documents.manage'],
                ['name' => 'Teklif/Görüntüleme Gör', 'slug' => 'interest.view'],
            ],
            // Kiracı portalı — yalnızca kendi sözleşmesi / ödemeleri
            'Portal (Kiracı)' => [
                ['name' => 'Kendi Sözleşmesini Gör', 'slug' => 'leases.view_own'],
                ['name' => 'Kendi Ödemelerini Gör', 'slug' => 'payments.view_own'],
                ['name' => 'Arıza Taleplerini Gör', 'slug' => 'maintenance.view_own'],
                ['name' => 'Arıza Talebi Oluştur', 'slug' => 'maintenance.create'],
            ],
            // Ev sahibi portalı — kendi mülkleri
            'Portal (Ev Sahibi)' => [
                ['name' => 'Mülk Sözleşmelerini Gör', 'slug' => 'leases.view_as_landlord'],
                ['name' => 'Mülk Ödemelerini Gör', 'slug' => 'payments.view_as_landlord'],
                ['name' => 'Kendi Mülklerini Gör', 'slug' => 'properties.view_own'],
                ['name' => 'Teklif/Görüntüleme (Kendi Mülk)', 'slug' => 'interest.view_own'],
            ],
            // Teknisyen portalı
            'Portal (Teknisyen)' => [
                ['name' => 'Arıza Taleplerini Gör (Teknisyen)', 'slug' => 'maintenance.view_as_technician'],
                ['name' => 'Arıza Talebi Onayla/Reddet', 'slug' => 'maintenance.decide'],
            ],
            // Personel — tüm arıza talepleri
            'Arıza Yönetimi (Personel)' => [
                ['name' => 'Tüm Arıza Taleplerini Gör', 'slug' => 'maintenance.view'],
            ],
            // Kiracı + ev sahibi ortak portal yetkileri
            'Portal (Ortak)' => [
                ['name' => 'Sözleşme Belgelerini Gör', 'slug' => 'documents.view'],
                ['name' => 'Mesajları Gör', 'slug' => 'messages.view'],
                ['name' => 'Mesaj Gönder', 'slug' => 'messages.send'],
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
