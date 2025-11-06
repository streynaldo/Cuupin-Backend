<?php

namespace Database\Seeders;

use App\Models\Bakery;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class BakeryWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owners = ['John Doe', 'Jane Smith', 'Alice Johnson', 'Bob Brown'];
        $banks = ['ID_BCA', 'ID_MANDIRI', 'ID_BRI', 'ID_BNI'];

        Bakery::doesntHave('wallet')->chunkById(100, function ($bakeries) use ($owners, $banks) {
            foreach ($bakeries as $bakery) {
                $bakery->wallet()->create([
                    'total_wallet'    => 0,
                    'total_earned'    => 0,
                    'total_withdrawn' => 0,
                    'no_rekening'     => $this->generateAccountNumber(),
                    'nama_bank'       => Arr::random($banks),
                    'nama_pemilik'    => Arr::random($owners),
                ]);
            }
        });
    }

    private function generateAccountNumber(): string
    {
        return '8291' . rand(100000000, 999999999);
    }
}
