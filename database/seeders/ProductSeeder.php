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
                'product_name'   => 'Lapis Surabaya Pandan Istimewa',
                'description'    => 'Lapis surabaya istimewa yang super lembut dengan rasa dan aroma pandan asli yang khas.',
                'price'          => 10000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Lapis+Surabaya+Pandan',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 1,
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Donat Klasik',
                'description'    => 'Donat spesial yang empuk dan mengembang sempurna, tersedia dengan pilihan topping lainnya.',
                'price'          => 9000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Donat+Klasik',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 1,
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Sausage Bread',
                'description'    => 'Roti dengan topping sosis, saus spesial, dan taburan keju. Gurih dan mengenyangkan!',
                'price'          => 15000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Sausage+Bread',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 1,
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Roti Abon Sapi Pedas Manis',
                'description'    => 'Roti iswimewa hadir dengan topping abon sapi premium dilengkapi dengan mayonnaise.',
                'price'          => 12000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Roti+Abon+Sapi',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 1,
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Bolu Gulung Tart Lemon',
                'description'    => 'Bolu gulung lembut, dengan cream manis pilihan.',
                'price'          => 15000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Bolu+Gulung+Lemon',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 1,
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Cheese Melt Bomb',
                'description'    => 'Roti buns super lembut, selembut bantal, diisi dengan cream cheese manis dan parutan keju premium yang melimpah.',
                'price'          => 15000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Cheese+Melt+Bomb',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 1,
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Paket Donat Ceria',
                'description'    => 'Nikmati donat klasik empuk khas Gosyen dalam satu paket hemat isi 4, dengan topping favorit mu!',
                'price'          => 28000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Paket+Donat+Ceria',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 2,
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Fluffy Turtle',
                'description'    => 'Roti fluffy berbentuk karakter menggemaskan dengan isian cokelat dan keju.',
                'price'          => 8000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Fluffy+Turtle',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 2,
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Roti Tawar Spesial',
                'description'    => 'Roti tawar yang lembut, dan padat, cocok untuk sarapan atau dibuat roti bakar.',
                'price'          => 16000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Roti+Tawar+Spesial',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 2,
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Lapis Surabaya Klasik',
                'description'    => 'Kue lapis klasik yang lembut dan moist, dengan aroma butter yang wangi dan legit.',
                'price'          => 10000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Lapis+Surabaya+Klasik',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 2,
                'discount_id'    => null,
            ],
            [
                'product_name'   => 'Roti Manis Pelangi',
                'description'    => 'Roti manis lembut dengan 3 flavour berbeda dalam satu gigitan!',
                'price'          => 13000,
                'best_before'    => 2,
                'image_url'      => 'https://placehold.co/300x300?text=Roti+Manis+Pelangi',
                'discount_price' => null,
                'status'         => 'available',
                'bakery_id'      => 2,
                'discount_id'    => null,
            ],
        ];

        foreach ($data as $item) {
            Product::create($item);
        }
    }
}
