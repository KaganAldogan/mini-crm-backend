<?php

namespace Database\Seeders;

use App\Models\MaintenanceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'password' => '123456',
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'danisman@gmail.com'],
            [
                'name' => 'Danışman',
                'password' => '123456',
                'role' => User::ROLE_CONSULTANT,
            ]
        );

        $technician = User::query()->updateOrCreate(
            ['email' => 'tesisatci@gmail.com'],
            [
                'name' => 'Teknisyen',
                'password' => '123456',
                'role' => User::ROLE_TECHNICIAN,
            ]
        );

        $categoryIds = MaintenanceCategory::query()->pluck('uid')->all();
        if ($categoryIds !== []) {
            $technician->maintenanceCategories()->sync($categoryIds);
        }
    }
}
