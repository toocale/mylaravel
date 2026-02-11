<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $org = Organization::first(); // Assumes OeeSeeder ran first
        if (!$org) {
            $org = Organization::create(['name' => 'Default Org', 'code' => 'DEF']);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@testoee.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'admin',
                'organization_id' => $org->id,
                'is_active' => true,
                'remember_token' => Str::random(10),
            ]
        );
        
        // Assign to Admin group
        $adminGroup = \App\Models\Group::where('name', 'Admin')->first();
        if ($adminGroup && !$admin->groups->contains($adminGroup->id)) {
            $admin->groups()->attach($adminGroup->id);
        }
        
         // Also update existing user 'janim' if likely dev to be admin
         $dev = User::where('email', 'like', '%janim%')->first();
         if ($dev) {
             $dev->update(['role' => 'admin']);
         }
    }
}
