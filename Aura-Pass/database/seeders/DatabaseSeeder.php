<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. ALWAYS CREATE THE SUPERADMIN
        User::firstOrCreate(
            ['username' => 'superadmin'], // Search by this
            [
                'password' => bcrypt('password123'), // Create with this
                'role' => 'superadmin'
            ]
        );

        // 2. RUN YOUR OTHER SEEDERS (Optional)
        // If you want fake data generated automatically, uncomment these:
        $this->call([
             CheckInSeeder::class,
         ]);
    }
}