<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DiscountEvent;
use App\Models\Product;

class ExpireDiscounts extends Command
{
    protected $signature = 'discounts:expire';
    protected $description = 'Clear expired discounts from products';

    public function handle()
    {
        $now = now();

        // Ambil semua event yang sudah berakhir
        $expiredEvents = DiscountEvent::where('discount_end_time', '<', $now)->get();

        $totalProductsUpdated = 0;

        foreach ($expiredEvents as $event) {
            $count = Product::where('discount_id', $event->id)
                ->update([
                    'discount_id' => null,
                    'discount_price' => null,
                ]);

            $totalProductsUpdated += $count;
        }

        $this->info("Expired discounts cleared. Updated products: {$totalProductsUpdated}");

        return Command::SUCCESS;
    }
}
