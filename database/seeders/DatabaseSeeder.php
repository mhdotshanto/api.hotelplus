<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            GuestSeeder::class,
        ]);
    }
}
// php artisan db:seed --class=GuestSeeder
