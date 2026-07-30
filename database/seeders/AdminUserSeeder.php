<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Pehla admin user banata hai admin panel login ke liye.
     * Chalayein: php artisan db:seed --class=AdminUserSeeder
     *
     * ⚠️ Production mein login karne ke turant baad ye password badal lein!
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@abwabalkheir.com'],
            [
                'name'     => 'Admin',
                'phone'    => '+971500000000',
                'password' => Hash::make('123456'),
                'is_admin' => true,
            ]
        );
    }
}
