<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@mini-crm.test'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'danisman@mini-crm.test'],
            [
                'name' => 'Danışman',
                'password' => 'password',
                'role' => User::ROLE_CONSULTANT,
            ]
        );
    }
}
