<?php

namespace Database\Seeders;

use App\Models\ListingType;
use Illuminate\Database\Seeder;

class ListingTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Satılık', 'slug' => 'sale', 'sort_order' => 1],
            ['name' => 'Kiralık', 'slug' => 'rent', 'sort_order' => 2],
        ];

        foreach ($items as $item) {
            ListingType::query()->updateOrCreate(
                ['slug' => $item['slug']],
                $item
            );
        }
    }
}
