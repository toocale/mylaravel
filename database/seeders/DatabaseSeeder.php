<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Essential system data that should ALWAYS exist
        $this->call([
            RbacSeeder::class,              // Permissions & Groups
            SiteSettingsSeeder::class,      // Site configuration
            AdminUserSeeder::class,         // Default admin account
        ]);

        // Optional: Seed test/demo data if in local environment
        if (app()->environment('local')) {
            $this->call([
                MaterialLossCategorySeeder::class,
                // ComprehensiveTestDataSeeder::class, // Uncomment for full test data
            ]);
        }
    }
}
