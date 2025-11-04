<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bakery;
use Illuminate\Support\Carbon;

class RefreshBakeryDiscountStatus extends Command
{
    protected $signature = 'bakeries:refresh-discount-status';
    protected $description = 'Sync bakery discount_status (active/inactive) based on active discount events';

    public function handle(): int
    {
        $now = Carbon::now();

        // Ambil semua bakery yang PUNYA event aktif lewat relasi products -> discountEvent
        $activeBakeryIds = Bakery::whereHas('products.discountEvent', function ($q) use ($now) {
                $q->where('discount_start_time', '<=', $now)
                  ->where('discount_end_time', '>=', $now);
            })
            ->pluck('id');

        // Set active untuk yang masuk list
        $affectedActive = Bakery::whereIn('id', $activeBakeryIds)
            ->where('discount_status', '!=', 'active')
            ->update(['discount_status' => 'active']);

        // Set inactive untuk sisanya
        $affectedInactive = Bakery::whereNotIn('id', $activeBakeryIds)
            ->where('discount_status', '!=', 'inactive')
            ->update(['discount_status' => 'inactive']);

        $this->info("Bakery discount_status updated. Active: {$affectedActive}, Inactive: {$affectedInactive}");
        return self::SUCCESS;
    }
}
