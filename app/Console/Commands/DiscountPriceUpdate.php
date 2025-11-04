<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DiscountEvent;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class DiscountPriceUpdate extends Command
{
    protected $signature = 'discounts:reprice-active {--event_id=} {--chunk=500} {--dry-run}';
    protected $description = 'Recalculate products.discount_price for all products attached to active discount events';

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk') ?: 500;
        $eventId = $this->option('event_id');
        $dryRun  = (bool) $this->option('dry-run');

        // Ambil event aktif (bisa filter 1 event via --event_id untuk debugging)
        $eventsQuery = DiscountEvent::query()->active();

        if ($eventId) {
            $eventsQuery->where('id', $eventId);
        }

        $events = $eventsQuery->get(['id', 'discount']);
        if ($events->isEmpty()) {
            $this->info('No active discount events. Nothing to reprice.');
            return self::SUCCESS;
        }

        $totalUpdated = 0;
        foreach ($events as $event) {
            $updatedForEvent = 0;

            // Ambil produk yang ter-attach ke event ini
            Product::where('discount_id', $event->id)
                ->orderBy('id')
                ->chunkById($chunk, function ($products) use ($event, $dryRun, &$updatedForEvent) {
                    // Transaksi per batch agar konsisten; kalau besar bisa dipertimbangkan tanpa transaksi
                    DB::transaction(function () use ($products, $event, $dryRun, &$updatedForEvent) {
                        foreach ($products as $product) {
                            $old = $product->discount_price;

                            if ($dryRun) {
                                // Simulasi perhitungan
                                $sim = (int) floor($product->price * (100 - (int)$event->discount) / 100);
                                if ($sim !== $old) {
                                    $updatedForEvent++;
                                }
                                continue;
                            }

                            // Gunakan util di model → rapi & reusable
                            $product->applyDiscountPercent((int) $event->discount);

                            if ($product->discount_price !== $old) {
                                $updatedForEvent++;
                            }
                        }
                    }, 3); // retry up to 3x bila deadlock ringan
                });

            $this->info("Event #{$event->id} re-priced products changed: {$updatedForEvent}");
            $totalUpdated += $updatedForEvent;
        }

        $this->info("TOTAL products re-priced: {$totalUpdated}");
        return self::SUCCESS;
    }
}
