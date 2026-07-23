<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $consultantId = User::query()
            ->where('email', 'danisman@mini-crm.test')
            ->value('uid');

        $items = [
            [
                'title' => 'Merkezde Satılık 3+1 Daire',
                'listing_type' => 'sale',
                'property_type' => 'apartment',
                'price' => 4_500_000,
                'location' => 'Kadıköy, İstanbul',
                'rooms' => '3+1',
                'area_sqm' => 145,
            ],
            [
                'title' => 'Bahçeli Müstakil Ev',
                'listing_type' => 'sale',
                'property_type' => 'house',
                'price' => 8_200_000,
                'location' => 'Çankaya, Ankara',
                'rooms' => '4+1',
                'area_sqm' => 220,
            ],
            [
                'title' => 'Yatırımlık Arsa',
                'listing_type' => 'sale',
                'property_type' => 'land',
                'price' => 3_100_000,
                'location' => 'Gölbaşı, Ankara',
                'rooms' => null,
                'area_sqm' => 850,
            ],
            [
                'title' => 'Kiralık 2+1 Daire',
                'listing_type' => 'rent',
                'property_type' => 'apartment',
                'price' => 28_000,
                'location' => 'Beşiktaş, İstanbul',
                'rooms' => '2+1',
                'area_sqm' => 95,
            ],
            [
                'title' => 'Kiralık Müstakil Ev',
                'listing_type' => 'rent',
                'property_type' => 'house',
                'price' => 45_000,
                'location' => 'Bornova, İzmir',
                'rooms' => '3+1',
                'area_sqm' => 180,
            ],
            [
                'title' => 'Caddede Kiralık Dükkan',
                'listing_type' => 'rent',
                'property_type' => 'shop',
                'price' => 55_000,
                'location' => 'Alsancak, İzmir',
                'rooms' => null,
                'area_sqm' => 120,
            ],
            [
                'title' => 'Plazada Satılık Ofis',
                'listing_type' => 'sale',
                'property_type' => 'office',
                'price' => 6_750_000,
                'location' => 'Levent, İstanbul',
                'rooms' => null,
                'area_sqm' => 160,
            ],
        ];

        foreach ($items as $item) {
            Property::query()->updateOrCreate(
                ['title' => $item['title']],
                [
                    ...$item,
                    'description' => 'Örnek portföy ilanı.',
                    'status' => 'active',
                    'user_id' => $consultantId,
                ]
            );
        }
    }
}
