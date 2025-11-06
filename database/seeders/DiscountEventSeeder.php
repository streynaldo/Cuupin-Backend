<?php

namespace Database\Seeders;

use App\Models\DiscountEvent;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DiscountEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            [
                'discount_name'       => 'Halloween Sale',
                'discount'            => 30,
                'discount_photo'      => 'https://example.com/halloween.jpg',
                'discount_start_time' => Carbon::now()->subDays(2),
                'discount_end_time'   => Carbon::now()->addDays(5),
                'bakery_id'            => 1
            ],
            [
                'discount_name'       => 'Year End Sale',
                'discount'            => 20,
                'discount_photo'      => 'https://example.com/yearend.jpg',
                'discount_start_time' => Carbon::now()->addDays(10),
                'discount_end_time'   => Carbon::now()->addDays(20),
                'bakery_id'            => 2
            ],
            [
                'discount_name'       => 'Year Start Sale',
                'discount'            => 45,
                'discount_photo'      => 'https://example.com/yearend.jpg',
                'discount_start_time' => Carbon::now()->addDays(10),
                'discount_end_time'   => Carbon::now()->addDays(20),
                'bakery_id'            => 3
            ],
        ];

        foreach ($events as $eventData) {
            $event = DiscountEvent::create($eventData);

            // ambil beberapa produk random untuk event ini
            $products = Product::inRandomOrder()->take(rand(2, 5))->get();

            foreach ($products as $product) {
                $discountedPrice = (int) round($product->price * (100 - $event->discount) / 100);

                $product->update([
                    'discount_id'    => $event->id,
                    'discount_price' => $discountedPrice,
                ]);
            }
        }
    }
}
