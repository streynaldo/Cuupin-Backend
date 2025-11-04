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

        // Bakery A — ada event aktif → harus jadi ACTIVE
        $bakeryActive = Bakery::create([
            'user_id' => 1,
            'name' => 'Active Bakery',
            'address' => 'Jl. Diskon Selalu',
            'is_active' => true,
            'discount_status' => 'inactive',
        ]);

        $event = DiscountEvent::create([
            'discount_name' => 'Flash Sale',
            'discount' => 20,
            'discount_start_time' => Carbon::now()->subMinutes(5),
            'discount_end_time'   => Carbon::now()->addMinutes(5),
        ]);

        Product::create([
            'bakery_id' => $bakeryActive->id,
            'product_name' => 'Roti Manis',
            'price' => 20000,
            'discount_id' => $event->id,
        ]);

        // Bakery B — tidak punya event → harus jadi INACTIVE
        Bakery::create([
            'user_id' => 1,
            'name' => 'Inactive Bakery',
            'address' => 'Jl. No Diskon',
            'is_active' => true,
            'discount_status' => 'active',
        ]);

        $this->command->info('✅ Test data generated for bakery discount_status refresh job');
    }
}
