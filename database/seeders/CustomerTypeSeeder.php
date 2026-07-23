<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use Illuminate\Database\Seeder;

class CustomerTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Aday', 'slug' => 'prospect', 'sort_order' => 1],
            ['name' => 'Ev sahibi', 'slug' => 'landlord', 'sort_order' => 2],
            ['name' => 'Kiracı', 'slug' => 'tenant', 'sort_order' => 3],
            ['name' => 'Ev sahibi + Kiracı', 'slug' => 'both', 'sort_order' => 4],
        ];

        foreach ($items as $item) {
            CustomerType::query()->updateOrCreate(
                ['slug' => $item['slug']],
                $item
            );
        }
    }
}
