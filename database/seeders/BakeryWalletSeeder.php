<?php

namespace Database\Seeders;

use App\Models\Bakery;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BakeryWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Bakery::doesntHave('wallet')->get()->each(function ($bakery) {
            $bakery->wallet()->create([
                'total_wallet' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'no_rekening' => $this->generateAccountNumber(),
                'nama_bank' => 'ID_BCA',
                'nama_pemilik' => array_rand(['John Doe', 'Jane Smith', 'Alice Johnson', 'Bob Brown']),
            ]);
        });
    }

    private function generateAccountNumber(): string
    {
        return '8291' . rand(100000000, 999999999);
    }
}
