<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Guest;

class GuestSeeder extends Seeder
{
    public function run(): void
    {
        $total = 1000000; // 1 million
        $batch = 10000;   // insert in chunks to avoid memory issues

        for ($i = 0; $i < $total / $batch; $i++) {
            Guest::factory()->count($batch)->create();
            echo "Inserted batch " . ($i + 1) . " of " . ($total / $batch) . "\n";
        }
    }
}
