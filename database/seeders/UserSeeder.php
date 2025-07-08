<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [ 
                'name' => 'Super Admin',
                'user_name' => 'super-admin', 
                'role' => 'super_admin', 
                'type' => 'user', 
                'status' => 'active',
                'email' => 'admin@gmail.com', 
                'phone' => '01934567890', 
                'designation' => 'super admin', 
                'password' => Hash::make('12345678'),
                'is_ref' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
