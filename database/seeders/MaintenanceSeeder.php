<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Machine;
use App\Models\User;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceLog;
use App\Models\MachineComponent;
use Carbon\Carbon;

class MaintenanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first machine and user for seeding
        $machine = Machine::first();
        $user = User::first();

        if (!$machine || !$user) {
            $this->command->error('No machines or users found. Please ensure you have machines and users in the database.');
            return;
        }

        $this->command->info("Seeding maintenance data for machine: {$machine->name}");

        // Clear existing data for this machine
        MaintenanceSchedule::where('machine_id', $machine->id)->delete();
        MaintenanceLog::where('machine_id', $machine->id)->delete();
        MachineComponent::where('machine_id', $machine->id)->delete();

        // === MAINTENANCE SCHEDULES ===
        $this->command->info('Creating maintenance schedules...');

        $schedules = [
            // Daily tasks
            [
                'task_name' => 'Daily Lubrication',
                'description' => 'Apply lubricant to moving parts and check oil levels',
                'maintenance_type' => 'daily',
                'frequency_days' => 1,
                'priority' => 'medium',
                'estimated_duration_minutes' => 15,
                'last_performed_at' => now()->subDays(1),
                'next_due_at' => now(),
                'is_overdue' => false,
            ],
            [
                'task_name' => 'Visual Inspection',
                'description' => 'Check for leaks, unusual sounds, and visual damage',
                'maintenance_type' => 'daily',
                'frequency_days' => 1,
                'priority' => 'low',
                'estimated_duration_minutes' => 10,
                'last_performed_at' => now()->subDays(2),
                'next_due_at' => now()->subDays(1),
                'is_overdue' => true, // Overdue by 1 day
            ],
            
            // Weekly tasks
            [
                'task_name' => 'Belt Tension Check',
                'description' => 'Inspect and adjust belt tension if necessary',
                'maintenance_type' => 'weekly',
                'frequency_days' => 7,
                'priority' => 'medium',
                'estimated_duration_minutes' => 30,
                'last_performed_at' => now()->subDays(7),
                'next_due_at' => now(),
                'is_overdue' => false,
            ],
            [
                'task_name' => 'Filter Cleaning',
                'description' => 'Clean or replace air and oil filters',
                'maintenance_type' => 'weekly',
                'frequency_days' => 7,
                'priority' => 'high',
                'estimated_duration_minutes' => 20,
                'last_performed_at' => now()->subDays(10),
                'next_due_at' => now()->subDays(3),
                'is_overdue' => true, // Overdue by 3 days
            ],
            
            // Monthly tasks
            [
                'task_name' => 'Comprehensive Safety Inspection',
                'description' => 'Full safety check including emergency stops, guards, and electrical systems',
                'maintenance_type' => 'monthly',
                'frequency_days' => 30,
                'priority' => 'critical',
                'estimated_duration_minutes' => 90,
                'last_performed_at' => now()->subDays(20),
                'next_due_at' => now()->addDays(10),
                'is_overdue' => false,
            ],
            [
                'task_name' => 'Bearing Inspection',
                'description' => 'Check bearing condition and temperature',
                'maintenance_type' => 'monthly',
                'frequency_days' => 30,
                'priority' => 'high',
                'estimated_duration_minutes' => 45,
                'last_performed_at' => now()->subDays(25),
                'next_due_at' => now()->addDays(5),
                'is_overdue' => false,
            ],
            
            // Quarterly tasks
            [
                'task_name' => 'Motor Alignment Check',
                'description' => 'Verify motor and drive alignment using laser alignment tool',
                'maintenance_type' => 'quarterly',
                'frequency_days' => 90,
                'priority' => 'high',
                'estimated_duration_minutes' => 120,
                'last_performed_at' => now()->subDays(75),
                'next_due_at' => now()->addDays(15),
                'is_overdue' => false,
            ],
            
            // Annual tasks
            [
                'task_name' => 'Complete Machine Overhaul',
                'description' => 'Full disassembly, cleaning, and replacement of wear parts',
                'maintenance_type' => 'annual',
                'frequency_days' => 365,
                'priority' => 'critical',
                'estimated_duration_minutes' => 480,
                'last_performed_at' => now()->subDays(350),
                'next_due_at' => now()->addDays(15),
                'is_overdue' => false,
            ],
        ];

        foreach ($schedules as $schedule) {
            MaintenanceSchedule::create([
                'machine_id' => $machine->id,
                'assigned_to_user_id' => $user->id,
                'is_active' => true,
                ...$schedule
            ]);
        }

        $this->command->info('✓ Created ' . count($schedules) . ' maintenance schedules');

        // === MAINTENANCE LOGS (History) ===
        $this->command->info('Creating maintenance history...');

        $logs = [
            [
                'performed_at' => now()->subDays(1),
                'task_description' => 'Daily Lubrication',
                'duration_minutes' => 12,
                'notes' => 'All lubrication points serviced. Oil levels good.',
                'parts_replaced' => null,
                'cost' => null,
            ],
            [
                'performed_at' => now()->subDays(3),
                'task_description' => 'Emergency Belt Replacement',
                'duration_minutes' => 45,
                'notes' => 'Belt showed signs of wear and cracking. Replaced with new belt.',
                'parts_replaced' => [
                    ['name' => 'V-Belt Type A', 'quantity' => 1, 'cost' => 45.50]
                ],
                'cost' => 45.50,
            ],
            [
                'performed_at' => now()->subDays(7),
                'task_description' => 'Weekly Filter Cleaning',
                'duration_minutes' => 25,
                'notes' => 'Filters cleaned and reinstalled. Airflow restored to normal.',
                'parts_replaced' => null,
                'cost' => null,
            ],
            [
                'performed_at' => now()->subDays(15),
                'task_description' => 'Sensor Calibration',
                'duration_minutes' => 60,
                'notes' => 'Recalibrated temperature and pressure sensors. All readings within spec.',
                'parts_replaced' => null,
                'cost' => null,
            ],
            [
                'performed_at' => now()->subDays(20),
                'task_description' => 'Motor Bearing Replacement',
                'duration_minutes' => 120,
                'notes' => 'Main motor bearing showing excessive wear. Replaced both front and rear bearings.',
                'parts_replaced' => [
                    ['name' => 'Deep Groove Ball Bearing 6205', 'quantity' => 2, 'cost' => 35.00]
                ],
                'cost' => 70.00,
            ],
            [
                'performed_at' => now()->subDays(30),
                'task_description' => 'Monthly Safety Inspection',
                'duration_minutes' => 85,
                'notes' => 'Complete safety inspection performed. All systems functioning correctly. Emergency stop tested.',
                'parts_replaced' => null,
                'cost' => null,
            ],
        ];

        foreach ($logs as $log) {
            MaintenanceLog::create([
                'machine_id' => $machine->id,
                'performed_by_user_id' => $user->id,
                ...$log
            ]);
        }

        $this->command->info('✓ Created ' . count($logs) . ' maintenance logs');

        // === MACHINE COMPONENTS ===
        $this->command->info('Creating machine components...');

        $components = [
            // Good condition
            [
                'component_name' => 'Main Drive Motor',
                'component_type' => 'motor',
                'manufacturer' => 'Siemens',
                'model_number' => '1LA7 133-4AA60',
                'serial_number' => 'SN-20231001',
                'installed_at' => now()->subMonths(6),
                'expected_lifespan_hours' => 10000,
                'current_runtime_hours' => 2500,
                'replacement_threshold_hours' => 9000,
                'status' => 'good',
                'last_inspected_at' => now()->subDays(30),
                'cost' => 1250.00,
            ],
            [
                'component_name' => 'Servo Drive',
                'component_type' => 'electronics',
                'manufacturer' => 'Allen Bradley',
                'model_number' => 'MPL-B330P-MJ72AA',
                'serial_number' => 'SN-20230815',
                'installed_at' => now()->subMonths(8),
                'expected_lifespan_hours' => 15000,
                'current_runtime_hours' => 3200,
                'replacement_threshold_hours' => 13500,
                'status' => 'good',
                'last_inspected_at' => now()->subDays(15),
                'cost' => 2800.00,
            ],
            
            // Warning condition
            [
                'component_name' => 'Hydraulic Pump',
                'component_type' => 'pump',
                'manufacturer' => 'Bosch Rexroth',
                'model_number' => 'A10VSO71',
                'serial_number' => 'SN-20220601',
                'installed_at' => now()->subMonths(18),
                'expected_lifespan_hours' => 8000,
                'current_runtime_hours' => 6500,
                'replacement_threshold_hours' => 7200,
                'status' => 'warning',
                'last_inspected_at' => now()->subDays(7),
                'cost' => 3500.00,
            ],
            [
                'component_name' => 'Timing Belt',
                'component_type' => 'belt',
                'manufacturer' => 'Gates',
                'model_number' => 'PowerGrip GT3',
                'serial_number' => 'SN-20230301',
                'installed_at' => now()->subMonths(12),
                'expected_lifespan_hours' => 5000,
                'current_runtime_hours' => 3800,
                'replacement_threshold_hours' => 4500,
                'status' => 'warning',
                'last_inspected_at' => now()->subDays(5),
                'cost' => 185.00,
            ],
            
            // Critical condition
            [
                'component_name' => 'Air Filter Element',
                'component_type' => 'filter',
                'manufacturer' => 'Parker',
                'model_number' => 'FF-125-10',
                'serial_number' => 'SN-20231115',
                'installed_at' => now()->subMonths(4),
                'expected_lifespan_hours' => 2000,
                'current_runtime_hours' => 1900,
                'replacement_threshold_hours' => 1800,
                'status' => 'critical',
                'last_inspected_at' => now()->subDays(2),
                'cost' => 75.00,
            ],
            [
                'component_name' => 'Proximity Sensor (Position 3)',
                'component_type' => 'sensor',
                'manufacturer' => 'Omron',
                'model_number' => 'E2E-X10MY1',
                'serial_number' => 'SN-20220910',
                'installed_at' => now()->subMonths(20),
                'expected_lifespan_hours' => 10000,
                'current_runtime_hours' => 9800,
                'replacement_threshold_hours' => 9000,
                'status' => 'critical',
                'last_inspected_at' => now()->subDays(1),
                'cost' => 125.00,
            ],
            
            // More components
            [
                'component_name' => 'Cooling Fan',
                'component_type' => 'fan',
                'manufacturer' => 'EBM-Papst',
                'model_number' => 'W2E200-HH86-01',
                'serial_number' => 'SN-20230520',
                'installed_at' => now()->subMonths(10),
                'expected_lifespan_hours' => 6000,
                'current_runtime_hours' => 1800,
                'replacement_threshold_hours' => 5400,
                'status' => 'good',
                'last_inspected_at' => now()->subDays(20),
                'cost' => 320.00,
            ],
            [
                'component_name' => 'Chain Drive Assembly',
                'component_type' => 'chain',
                'manufacturer' => 'Renold',
                'model_number' => 'SD40-1-3/4',
                'serial_number' => 'SN-20221201',
                'installed_at' => now()->subMonths(16),
                'expected_lifespan_hours' => 7000,
                'current_runtime_hours' => 5200,
                'replacement_threshold_hours' => 6300,
                'status' => 'warning',
                'last_inspected_at' => now()->subDays(10),
                'cost' => 450.00,
            ],
        ];

        foreach ($components as $component) {
            MachineComponent::create([
                'machine_id' => $machine->id,
                ...$component
            ]);
        }

        $this->command->info('✓ Created ' . count($components) . ' machine components');
        
        // Summary
        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('Maintenance Seeding Complete!');
        $this->command->info('========================================');
        $this->command->info("Machine: {$machine->name} (ID: {$machine->id})");
        $this->command->info('Schedules: ' . count($schedules) . ' (2 overdue)');
        $this->command->info('History Logs: ' . count($logs));
        $this->command->info('Components: ' . count($components) . ' (2 critical, 3 warning, 3 good)');
        $this->command->info('');
        $this->command->info('Navigate to the Health tab to see the data!');
        $this->command->info('========================================');
    }
}
