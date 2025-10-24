<?php

namespace Database\Seeders;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BakerySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ambil user dengan role owner
        $owner = User::where('role', 'owner')->first();

        if (!$owner) {
            $this->command->warn('⚠️ Tidak ditemukan user dengan role "owner". Jalankan UserSeeder dulu.');
            return;
        }

        $data = [
            [
                'user_id'     => $owner->id,
                'name'        => 'Roti Mantul Bakery',
                'description' => 'Bakery lokal dengan roti lembut, fresh setiap pagi.',
                'logo_url'    => 'https://placehold.co/100x100?text=Logo+Roti+Mantul',
                'banner_url'  => 'https://placehold.co/600x200?text=Banner+Roti+Mantul',
                'address'     => 'Jl. Kenangan No. 12, Surabaya',
                'latitude'    => -7.2575,
                'longitude'   => 112.7521,
                'contact_info'=> '0812-3456-7890',
                'is_active'   => true,
            ],
            [
                'user_id'     => $owner->id,
                'name'        => 'Sweet Crumbs Bakery',
                'description' => 'Spesialis roti kering, cookies, dan pastry dengan resep Eropa.',
                'logo_url'    => 'https://placehold.co/100x100?text=Sweet+Crumbs',
                'banner_url'  => 'https://placehold.co/600x200?text=Sweet+Crumbs',
                'address'     => 'Jl. Mawar No. 45, Bandung',
                'latitude'    => -6.9147,
                'longitude'   => 107.6098,
                'contact_info'=> '0813-9988-1122',
                'is_active'   => true,
            ],
            [
                'user_id'     => $owner->id,
                'name'        => 'Dough & Co.',
                'description' => 'Modern bakery dengan konsep open kitchen dan bahan premium.',
                'logo_url'    => 'https://placehold.co/100x100?text=Dough+%26+Co',
                'banner_url'  => 'https://placehold.co/600x200?text=Dough+%26+Co',
                'address'     => 'Jl. Diponegoro No. 8, Jakarta',
                'latitude'    => -6.2088,
                'longitude'   => 106.8456,
                'contact_info'=> '0878-1122-3344',
                'is_active'   => false,
            ],
        ];

        foreach ($data as $item) {
            Bakery::create($item);
        }
    }
}
