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
        $owner = User::where('role', 'owner')->get();
        $owner1 = $owner->where('name', 'owner demo')->first();
        $owner2 = $owner->where('name', 'owner 2 demo')->first();

        if (!$owner) {
            $this->command->warn('⚠️ Tidak ditemukan user dengan role "owner". Jalankan UserSeeder dulu.');
            return;
        }

        $data = [
            [
                'user_id'     => $owner1->id,
                'name'        => 'Doughlicious',
                'description' => 'Bakery lokal dengan roti lembut, fresh setiap pagi.',
                'logo_url'    => 'https://placehold.co/100x100?text=Logo+Roti+Mantul',
                'banner_url'  => 'https://placehold.co/600x200?text=Banner+Roti+Mantul',
                'address'     => 'Jl. Kenangan No. 12, Surabaya',
                'latitude'    => -7.2575,
                'longitude'   => 112.7521,
                'contact_info'=> '0812-3456-7890',
                'discount_status'=> 'inactive',
                'is_active'   => true,
            ],
            [
                'user_id'     => $owner2->id,
                'name'        => 'Gosyen',
                'description' => 'Spesialis roti kering, cookies, dan pastry dengan resep Eropa.',
                'logo_url'    => 'https://placehold.co/100x100?text=Sweet+Crumbs',
                'banner_url'  => 'https://placehold.co/600x200?text=Sweet+Crumbs',
                'address'     => 'Jl. Mawar No. 45, Bandung',
                'latitude'    => -6.9147,
                'longitude'   => 107.6098,
                'contact_info'=> '0813-9988-1122',
                'discount_status'=> 'inactive',
                'is_active'   => true,
            ],
        ];

        foreach ($data as $item) {
            Bakery::create($item);
        }
    }
}
