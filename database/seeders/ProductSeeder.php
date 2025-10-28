<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use App\Models\Product;
use App\Models\Bakery;
use App\Models\DiscountEvent;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bakeries = Bakery::pluck('id')->all();
        $discounts = DiscountEvent::pluck('id')->all();

        if (empty($bakeries)) {
            $this->command->warn('⚠️ Tidak ada bakery di database. Jalankan BakerySeeder dulu.');
            return;
        }

        $data = [
            [
                'product_name'   => 'Roti Tawar Lembut',
                'description'    => 'Roti tawar lembut khas rumahan, cocok untuk sarapan.',
                'price'          => 15000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Roti+Tawar',
                'discount_price' => null,
                'bakery_id'      => Arr::random($bakeries),
                'discount_id'    => $discounts ? Arr::random($discounts) : null,
            ],
            [
                'product_name'   => 'Croissant Mentega',
                'description'    => 'Croissant buttery flaky layers yang renyah dan gurih.',
                'price'          => 22000,
                'best_before'    => 1,
                'image_url'      => 'https://placehold.co/300x300?text=Croissant',
                'discount_price' => null,
                'bakery_id'      => Arr::random($bakeries),
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Donat Gula',
                'description'    => 'Donat klasik tabur gula halus, empuk dan manis.',
                'price'          => 12000,
                'best_before'    => 1,
                'image_url'      => 'https://placehold.co/300x300?text=Donat+Gula',
                'discount_price' => null,
                'bakery_id'      => Arr::random($bakeries),
                'discount_id'    => $discounts ? Arr::random($discounts) : null,
            ],
            [
                'product_name'   => 'Brownies Coklat',
                'description'    => 'Brownies legit dengan dark chocolate asli.',
                'price'          => 30000,
                'best_before'    => 3,
                'image_url'      => 'https://placehold.co/300x300?text=Brownies',
                'discount_price' => null,
                'bakery_id'      => Arr::random($bakeries),
                'discount_id'    => null,
            ],
        ];

        foreach ($data as $item) {
            Product::create($item);
        }
    }
}
