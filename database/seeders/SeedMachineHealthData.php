<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Machine;
use App\Models\User;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceLog;
use App\Models\MachineComponent;
use Carbon\Carbon;

class SeedMachineHealthData extends Seeder
{
    /**
     * Seed health data for a specific machine
     */
    public function run(): void
    {
        // CHANGE THIS to the machine ID you want to seed
        $machineId = 3; // Machine A3
        
        $machine = Machine::find($machineId);
        $user = User::first();

        if (!$machine) {
            $this->command->error("Machine ID {$machineId} not found.");
            return;
        }

        if (!$user) {
            $this->command->error('No users found in database.');
            return;
        }

        $this->command->info("Seeding maintenance data for: {$machine->name} (ID: {$machineId})");

        // Clear existing data for this machine
        MaintenanceSchedule::where('machine_id', $machineId)->delete();
        MaintenanceLog::where('machine_id', $machineId)->delete();
        MachineComponent::where('machine_id', $machineId)->delete();

        // Create 2 overdue tasks
        MaintenanceSchedule::create([
            'machine_id' => $machineId,
            'task_name' => 'Visual Inspection',
            'description' => 'Check for leaks, unusual sounds, and visual damage',
            'maintenance_type' => 'daily',
            'frequency_days' => 1,
            'priority' => 'low',
            'estimated_duration_minutes' => 10,
            'last_performed_at' => now()->subDays(2),
            'next_due_at' => now()->subDays(1),
            'is_overdue' => true,
            'assigned_to_user_id' => $user->id,
            'is_active' => true,
        ]);

        MaintenanceSchedule::create([
            'machine_id' => $machineId,
            'task_name' => 'Filter Cleaning',
            'description' => 'Clean or replace air and oil filters',
            'maintenance_type' => 'weekly',
            'frequency_days' => 7,
            'priority' => 'high',
            'estimated_duration_minutes' => 20,
            'last_performed_at' => now()->subDays(10),
            'next_due_at' => now()->subDays(3),
            'is_overdue' => true,
            'assigned_to_user_id' => $user->id,
            'is_active' => true,
        ]);

        // Create upcoming tasks
        for ($i = 0; $i < 4; $i++) {
            MaintenanceSchedule::create([
                'machine_id' => $machineId,
                'task_name' => ['Lubrication', 'Belt Check', 'Safety Inspection', 'Bearing Check'][$i],
                'description' => 'Routine maintenance task',
                'maintenance_type' => 'weekly',
                'frequency_days' => 7,
                'priority' => 'medium',
                'estimated_duration_minutes' => 30,
                'last_performed_at' => now()->subDays(7),
                'next_due_at' => now()->addDays($i + 1),
                'is_overdue' => false,
                'assigned_to_user_id' => $user->id,
                'is_active' => true,
            ]);
        }

        // Create maintenance logs
        for ($i = 0; $i < 5; $i++) {
            MaintenanceLog::create([
                'machine_id' => $machineId,
                'performed_by_user_id' => $user->id,
                'performed_at' => now()->subDays($i * 5 + 1),
                'task_description' => 'Routine Maintenance #' . ($i + 1),
                'duration_minutes' => rand(15, 90),
                'notes' => 'Completed successfully',
                'parts_replaced' => null,
                'cost' => null,
            ]);
        }

        // Create components
        $components = [
            ['name' => 'Main Motor', 'type' => 'motor', 'hours' => 2000, 'max' => 10000, 'status' => 'good'],
            ['name' => 'Drive Belt', 'type' => 'belt', 'hours' => 3500, 'max' => 5000, 'status' => 'warning'],
            ['name' => 'Air Filter', 'type' => 'filter', 'hours' => 1850, 'max' => 2000, 'status' => 'critical'],
            ['name' => 'Hydraulic Pump', 'type' => 'pump', 'hours' => 4000, 'max' => 8000, 'status' => 'good'],
            ['name' => 'Proximity Sensor', 'type' => 'sensor', 'hours' => 9500, 'max' => 10000, 'status' => 'critical'],
        ];

        foreach ($components as $comp) {
            MachineComponent::create([
                'machine_id' => $machineId,
                'component_name' => $comp['name'],
                'component_type' => $comp['type'],
                'manufacturer' => 'Generic Corp',
                'installed_at' => now()->subMonths(12),
                'expected_lifespan_hours' => $comp['max'],
                'current_runtime_hours' => $comp['hours'],
                'replacement_threshold_hours' => $comp['max'] * 0.9,
                'status' => $comp['status'],
                'last_inspected_at' => now()->subDays(5),
                'cost' => rand(100, 500),
            ]);
        }

        $this->command->info('✅ Successfully seeded data for ' . $machine->name);
        $this->command->info('   - 6 Maintenance Schedules (2 overdue)');
        $this->command->info('   - 5 Maintenance Logs');
        $this->command->info('   - 5 Components (2 critical)');
    }
}
