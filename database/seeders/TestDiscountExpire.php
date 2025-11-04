<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DiscountEvent;
use App\Models\Product;
use Carbon\Carbon;

class TestDiscountExpire extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Discount Event aktif (5 menit dari sekarang)
        $event = DiscountEvent::create([
            'discount_name'       => 'Flash Sale Bread',
            'discount'            => 30, // 30%
            'discount_photo'      => 'https://example.com/img/bread-sale.png',
            'discount_start_time' => Carbon::now()->subMinutes(1),
            'discount_end_time'   => Carbon::now()->addMinutes(5),
        ]);

        // Buat 2 produk untuk bakery_id = 1
        $product1 = Product::create([
            'bakery_id'    => 1,
            'product_name' => 'Chocolate Donut',
            'description'  => 'Sweet soft donut',
            'price'        => 25000,
            'best_before'  => 1,
        ]);

        $product2 = Product::create([
            'bakery_id'    => 1,
            'product_name' => 'Cheese Croissant',
            'description'  => 'Buttery and crispy',
            'price'        => 30000,
            'best_before'  => 2,
        ]);

        // Apply discount ke kedua product
        foreach ([$product1, $product2] as $product) {
            $product->discount_id = $event->id;
            $product->discount_price = round($product->price * (1 - ($event->discount / 100)));
            $product->save();
        }

        // Log hasil
        $this->command->info("✅ DiscountEventSeeder: 1 event + 2 products with discount applied");
    }
}
