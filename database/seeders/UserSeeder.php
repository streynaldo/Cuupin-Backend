<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'Joseph Karunia Wijaya',
                'email' => 'admin@test.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ],
            [
                'name' => 'owner demo',
                'email' => 'owner@test.com',
                'password' => Hash::make('password'),
                'role' => 'owner',
            ],
            [
                'name' => 'customer demo',
                'email' => 'customer@test.com',
                'password' => Hash::make('password'),
                'role' => 'customer',
            ],
        ];

        foreach ($data as $user) {
            User::create($user);
        }
    }
}
