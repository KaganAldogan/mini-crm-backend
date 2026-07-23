<?php

namespace Database\Seeders;

use App\Models\MaintenanceCategory;
use Illuminate\Database\Seeder;

class MaintenanceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Su / Tesisat', 'slug' => 'plumbing', 'sort_order' => 1],
            ['name' => 'Elektrik', 'slug' => 'electrical', 'sort_order' => 2],
            ['name' => 'Isınma / Klima', 'slug' => 'heating', 'sort_order' => 3],
            ['name' => 'Beyaz Eşya', 'slug' => 'appliance', 'sort_order' => 4],
            ['name' => 'Yapı / İnşaat', 'slug' => 'structural', 'sort_order' => 5],
            ['name' => 'Diğer', 'slug' => 'other', 'sort_order' => 6],
        ];

        foreach ($items as $item) {
            MaintenanceCategory::query()->updateOrCreate(
                ['slug' => $item['slug']],
                $item
            );
        }
    }
}
