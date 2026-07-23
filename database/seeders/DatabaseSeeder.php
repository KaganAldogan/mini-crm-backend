<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            ListingTypeSeeder::class,
            PropertyTypeSeeder::class,
            CustomerTypeSeeder::class,
            MaintenanceCategorySeeder::class,
            UserSeeder::class,
            PropertySeeder::class,
        ]);
    }
}
