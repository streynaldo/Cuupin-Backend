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
                'nama_bank' => 'Bank Cuupin',
                'nama_pemilik' => $bakery->name,
            ]);
        });
    }

    private function generateAccountNumber(): string
    {
        return '8291' . rand(100000000, 999999999);
    }
}
