<?php

namespace Database\Seeders;

use App\Models\PropertyType;
use Illuminate\Database\Seeder;

class PropertyTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Daire', 'slug' => 'apartment', 'sort_order' => 1],
            ['name' => 'Ev', 'slug' => 'house', 'sort_order' => 2],
            ['name' => 'Arsa', 'slug' => 'land', 'sort_order' => 3],
            ['name' => 'Ofis', 'slug' => 'office', 'sort_order' => 4],
            ['name' => 'Dükkan', 'slug' => 'shop', 'sort_order' => 5],
        ];

        foreach ($items as $item) {
            PropertyType::query()->updateOrCreate(
                ['slug' => $item['slug']],
                $item
            );
        }
    }
}
