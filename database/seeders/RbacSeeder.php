<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Permissions
        $permissions = [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'groups.manage',
            'oee.view', 'oee.manage_config', 
            'reports.view',
            'assets.create', 'assets.update', 'assets.delete',
            
            // Product & Shift Management (Granular)
            'products.create', 'products.edit', 'products.delete',
            'shifts.create', 'shifts.edit', 'shifts.delete',
            'targets.create', 'targets.edit', 'targets.delete',

            // Maintenance Permissions
            'maintenance.view', 'maintenance.create', 'maintenance.edit', 'maintenance.delete',

            'kiosk.view', 'shift.manage', // Operator Permissions
        ];

        $permMap = [];
        foreach ($permissions as $p) {
            $permMap[$p] = \App\Models\Permission::firstOrCreate(['name' => $p])->id;
        }

        // 2. Create Groups
        $adminGroup = \App\Models\Group::firstOrCreate(['name' => 'Admin']);
        $managerGroup = \App\Models\Group::firstOrCreate(['name' => 'Plant Manager']);
        $operatorGroup = \App\Models\Group::firstOrCreate(['name' => 'Operator']);
        $viewerGroup = \App\Models\Group::firstOrCreate(['name' => 'Viewer']);

        // 3. Assign Permissions
        // Admin gets all
        $adminGroup->permissions()->sync(array_values($permMap));

        // Manager gets OEE management, Reports, and Kiosk access
        $managerGroup->permissions()->sync([
            $permMap['oee.view'], $permMap['oee.manage_config'], $permMap['reports.view'], $permMap['users.view'],
            $permMap['oee.view'], $permMap['oee.manage_config'], $permMap['reports.view'], $permMap['users.view'],
            $permMap['kiosk.view'], $permMap['shift.manage'],
            $permMap['products.create'], $permMap['products.edit'], $permMap['products.delete'],
            $permMap['shifts.create'], $permMap['shifts.edit'], $permMap['shifts.delete'],
            $permMap['shifts.create'], $permMap['shifts.edit'], $permMap['shifts.delete'],
            $permMap['shifts.create'], $permMap['shifts.edit'], $permMap['shifts.delete'],
            $permMap['targets.create'], $permMap['targets.edit'], $permMap['targets.delete'],
            $permMap['maintenance.view'], $permMap['maintenance.create'], $permMap['maintenance.edit'], $permMap['maintenance.delete'],
        ]);

        // Operator gets Kiosk View and Shift Manage (NO OEE/Dashboard View)
        $operatorGroup->permissions()->sync([
            $permMap['kiosk.view'],
            $permMap['shift.manage'],
            $permMap['maintenance.view']
        ]);

        // Viewer gets View OEE & Reports
        $viewerGroup->permissions()->sync([
            $permMap['oee.view'], $permMap['reports.view'], $permMap['maintenance.view']
        ]);

        // 4. Assign Admin User to Admin Group
        $adminUser = \App\Models\User::where('email', 'admin@dawaoee.com')->first();
        if ($adminUser && !$adminUser->groups()->where('name', 'Admin')->exists()) {
            $adminUser->groups()->attach($adminGroup->id);
        }
        
        // Assign Developer (janim) to Admin Group
        $devUser = \App\Models\User::where('email', 'like', 'janim%')->first();
        if ($devUser && !$devUser->groups()->where('name', 'Admin')->exists()) {
            $devUser->groups()->attach($adminGroup->id);
        }
    }
}
