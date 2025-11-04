<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bakery;
use App\Models\Product;
use App\Models\DiscountEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TestBakeryCronSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Bakery::truncate();
        Product::truncate();
        DiscountEvent::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = Carbon::now();

        // --- Fallback minimal data kalau belum ada produk/bakery ---
        if (Product::count() === 0) {
            $bakery = Bakery::first() ?? Bakery::create([
                'user_id'         => 1,
                'name'            => 'Seeder Bakery',
                'address'         => 'Jl. Seeder No. 1',
                'is_active'       => true,
                'discount_status' => 'inactive',
            ]);

            // bikin 5 produk dummy
            for ($i = 1; $i <= 5; $i++) {
                Product::create([
                    'bakery_id'    => $bakery->id,
                    'product_name' => "Produk {$i}",
                    'price'        => 10000 + $i * 1000,
                    'best_before'  => null,
                    'image_url'    => null,
                    'discount_id'  => null,
                    'discount_price' => null,
                ]);
            }
        }

        // --- Event 1: Halloween (SUDAH BERAKHIR kemarin) ---
        $halloween = DiscountEvent::create([
            'discount_name'       => 'Halloween Sale',
            'discount'            => 50,
            'discount_photo'      => 'https://example.com/halloween.jpg',
            'discount_start_time' => $now->copy()->subDays(3),
            'discount_end_time'   => $now->copy()->subDay(), // kemarin
        ]);

        // tempelkan beberapa produk ke event yg SUDAH BERAKHIR (biar nanti di-clear oleh discounts:expire)
        $expiredProducts = Product::inRandomOrder()->take(2)->get();
        foreach ($expiredProducts as $product) {
            $discounted = (int) round($product->price * (100 - $halloween->discount) / 100);
            $product->update([
                'discount_id'    => $halloween->id,
                'discount_price' => $discounted,
            ]);
        }

        // --- Event 2: Year End (AKTIF mulai SEKARANG) ---
        $yearEnd = DiscountEvent::create([
            'discount_name'       => 'Year End Sale',
            'discount'            => 60,
            'discount_photo'      => 'https://example.com/yearend.jpg',
            'discount_start_time' => $now->copy()->subMinute(), // mulai sekarang (aktif)
            'discount_end_time'   => $now->copy()->addDays(7),
        ]);

        // tempelkan beberapa produk ke event AKTIF (biar bakery jadi active oleh bakeries:refresh-discount-status)
        $activeProducts = Product::inRandomOrder()->take(3)->get();
        foreach ($activeProducts as $product) {
            $discounted = (int) round($product->price * (100 - $yearEnd->discount) / 100);
            $product->update([
                'discount_id'    => $yearEnd->id,
                'discount_price' => $discounted,
            ]);
        }
        $this->command->info('✅ DiscountEventSeeder ready: 1 expired event + 1 active event, products attached.');
    }
}
