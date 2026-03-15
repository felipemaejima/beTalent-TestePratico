<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@email.com'],
            [
                'name' => 'Felipe Maejima',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );
    }
}
