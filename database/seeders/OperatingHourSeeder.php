<?php

namespace Database\Seeders;

use App\Models\Bakery;
use App\Models\OperatingHour;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OperatingHourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil semua bakery untuk diberi jadwal
        $bakeries = Bakery::all();

        // Jika belum ada bakery, hentikan
        if ($bakeries->isEmpty()) {
            $this->command->warn('⚠️  Tidak ada bakery ditemukan. Jalankan BakerySeeder dulu.');
            return;
        }

        foreach ($bakeries as $bakery) {
            $hours = [];

            // Buat jam operasional untuk 7 hari (1 = Monday ... 7 = Sunday)
            for ($day = 1; $day <= 7; $day++) {
                // Random tutup di hari Minggu, sisanya buka
                $isClosed = ($day === 7);

                $hours[] = [
                    'bakery_id'       => $bakery->id,
                    'day_of_the_week' => $day,
                    'is_closed'       => $isClosed,
                    'open_time'       => $isClosed ? null : '08:00',
                    'close_time'      => $isClosed ? null : '17:00',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }

            OperatingHour::insert($hours);
        }
    }
}
