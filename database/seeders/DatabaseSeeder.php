<?php

namespace Database\Seeders;

use App\Models\Counter;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * 5 loket pendaftaran umum.
     */
    public function run(): void
    {
        foreach (range(1, 5) as $n) {
            Counter::updateOrCreate(
                ['counter_number' => $n],
                ['code' => (string) $n, 'is_active' => true],
            );
        }
    }
}
