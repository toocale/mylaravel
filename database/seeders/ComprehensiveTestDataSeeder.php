<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\Plant;
use App\Models\Line;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Shift;
use App\Models\ReasonCode;
use App\Models\MaterialLossCategory;
use App\Models\ProductionShift;
use App\Models\DowntimeEvent;
use App\Models\MaterialLoss;
use App\Models\DailyOeeMetric;
use App\Models\MachineProductConfig;
use Carbon\Carbon;

class ComprehensiveTestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: Get or create organization
        $organization = Organization::firstOrCreate(
            ['code' => 'TEST001'],
            [
                'name' => 'Test Manufacturing Corp',
            ]
        );

        $this->command->info("✓ Organization created/found: {$organization->name}");

        // Step 2: Create Plants
        $plants = [];
        $plantNames = [
            'Main Production Facility',
            'Secondary Assembly Plant',
            'Packaging Center',
        ];

        foreach ($plantNames as $name) {
            $plants[] = Plant::firstOrCreate(
                ['name' => $name, 'organization_id' => $organization->id],
                ['organization_id' => $organization->id]
            );
        }

        $this->command->info("✓ Created " . count($plants) . " plants");

        // Step 3: Create Shifts (3 shifts per day)
        $shifts = [];
        $shiftData = [
            ['name' => 'Morning Shift', 'type' => 'day', 'start_time' => '06:00:00', 'end_time' => '14:00:00'],
            ['name' => 'Afternoon Shift', 'type' => 'day', 'start_time' => '14:00:00', 'end_time' => '22:00:00'],
            ['name' => 'Night Shift', 'type' => 'night', 'start_time' => '22:00:00', 'end_time' => '06:00:00'],
        ];

        foreach ($plants as $plant) {
            foreach ($shiftData as $data) {
                $shifts[] = Shift::firstOrCreate(
                    ['name' => $data['name'], 'plant_id' => $plant->id],
                    array_merge($data, ['plant_id' => $plant->id])
                );
            }
        }

        $this->command->info("✓ Created " . count($shifts) . " shifts");

        // Step 4: Create Products
        $products = [];
        $productNames = [
            'Widget Alpha',
            'Widget Beta',
            'Gadget Pro',
            'Component X',
            'Assembly Kit',
        ];

        foreach ($productNames as $index => $name) {
            $products[] = Product::firstOrCreate(
                ['name' => $name, 'organization_id' => $organization->id],
                ['organization_id' => $organization->id, 'sku' => 'SKU-' . str_pad($index +1, 3, '0', STR_PAD_LEFT)]
            );
        }

        $this->command->info("✓ Created " . count($products) . " products");

        // Step 5: Create Reason Codes
        $reasonCodes = [];
        $reasonData = [
            ['code' => 'BRK-001', 'description' => 'Scheduled Break', 'category' => 'planned'],
            ['code' => 'MAINT-001', 'description' => 'Preventive Maintenance', 'category' => 'planned'],
            ['code' => 'FAIL-001', 'description' => 'Equipment Failure', 'category' => 'unplanned'],
            ['code' => 'JAM-001', 'description' => 'Material Jam', 'category' => 'unplanned'],
            ['code' => 'SETUP-001', 'description' => 'Product Changeover', 'category' => 'planned'],
            ['code' => 'NOMAT-001', 'description' => 'No Material', 'category' => 'unplanned'],
        ];

        foreach ($reasonData as $data) {
            $reasonCodes[] = ReasonCode::firstOrCreate(
                ['code' => $data['code'], 'organization_id' => $organization->id],
                array_merge($data, ['organization_id' => $organization->id])
            );
        }

        $this->command->info("✓ Created " . count($reasonCodes) . " reason codes");

        // Step 6: Create Material Loss Categories
        $lossCategories = [];
        $lossCategoryData = [
            ['code' => 'PKG_WASTE', 'name' => 'Package Waste', 'unit' => 'kg', 'affects_oee' => true, 'requires_reason' => true],
            ['code' => 'SPILL', 'name' => 'Spillage', 'unit' => 'liters', 'affects_oee' => true, 'requires_reason' => true],
            ['code' => 'SCRAP', 'name' => 'Production Scrap', 'unit' => 'units', 'affects_oee' => true, 'requires_reason' => false],
            ['code' => 'SAMPLE', 'name' => 'Quality Sampling', 'unit' => 'units', 'affects_oee' => false, 'requires_reason' => false],
        ];

        foreach ($lossCategoryData as $data) {
            $lossCategories[] = MaterialLossCategory::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['active' => true, 'description' => ''])
            );
        }

        $this->command->info("✓ Created " . count($lossCategories) . " material loss categories");

        // Step 7: Create Lines and Machines
        $machines = [];
        $lineData = [
            'Main Production Facility' => [
                ['name' => 'Assembly Line 1', 'machines' => ['Machine A1', 'Machine A2', 'Machine A3']],
                ['name' => 'Assembly Line 2', 'machines' => ['Machine B1', 'Machine B2']],
            ],
            'Secondary Assembly Plant' => [
                ['name' => 'Packaging Line', 'machines' => ['Packer 1', 'Packer 2', 'Labeler 1']],
            ],
            'Packaging Center' => [
                ['name' => 'Final Assembly', 'machines' => ['Assembler 1', 'Assembler 2']],
            ],
        ];

        foreach ($plants as $plant) {
            if (isset($lineData[$plant->name])) {
                foreach ($lineData[$plant->name] as $lineInfo) {
                    $line = Line::firstOrCreate(
                        ['name' => $lineInfo['name'], 'plant_id' => $plant->id],
                        ['plant_id' => $plant->id]
                    );

                    foreach ($lineInfo['machines'] as $index => $machineName) {
                        $machine = Machine::firstOrCreate(
                            ['name' => $machineName, 'line_id' => $line->id],
                            [
                                'line_id' => $line->id,
                                'default_ideal_rate' => rand(50, 150),
                            ]
                        );

                        $machines[] = $machine;

                        // Assign products to machines
                        foreach ($products as $productIndex => $product) {
                            if ($productIndex < 3) { // Assign first 3 products to each machine
                                MachineProductConfig::firstOrCreate(
                                    ['machine_id' => $machine->id, 'product_id' => $product->id],
                                    [
                                        'ideal_rate' => rand(60, 120),
                                    ]
                                );
                            }
                        }
                    }
                }
            }
        }

        $this->command->info("✓ Created " . count($machines) . " machines with product configurations");

        // Step 8: Create Production Shifts (last 30 days)
        $productionShifts = [];
        $startDate = Carbon::now()->subDays(30);
        
        $this->command->info("Creating production shifts and related data...");
        $progressBar = $this->command->getOutput()->createProgressBar(30);

        for ($day = 0; $day < 30; $day++) {
            $currentDate = $startDate->copy()->addDays($day);
            
            // Create 2-3 shifts per day for random machines
            $shiftsPerDay = rand(2, 4);
            
            for ($i = 0; $i < $shiftsPerDay; $i++) {
                $machine = $machines[array_rand($machines)];
                $shift = $shifts[array_rand($shifts)];
                $product = $products[array_rand($products)];
                
                // Create shift start time
                $shiftStart = $currentDate->copy()->setTimeFromTimeString($shift->start_time);
                $shiftEnd = $shiftStart->copy()->addHours(8);
                
                // Skip if in future
                if ($shiftStart->isFuture()) continue;
                
                $goodCount = rand(400, 950);
                $rejectCount = rand(10, 50);
                $idealRate = rand(60, 120);
                
                $productionShift = ProductionShift::create([
                    'machine_id' => $machine->id,
                    'user_id' => 1, // Admin user
                    'shift_id' => $shift->id,
                    'product_id' => $product->id,
                    'started_at' => $shiftStart,
                    'ended_at' => $shiftEnd,
                    'status' => 'completed',
                    'user_group' => 'Operator',
                    'metadata' => [
                        'good_count' => $goodCount,
                        'reject_count' => $rejectCount,
                        'total_output' => $goodCount + $rejectCount,
                        'target_output' => rand(800, 1000),
                        'ideal_rate' => $idealRate,
                        'downtime_minutes' => rand(10, 60),
                    ]
                ]);
                
                $productionShifts[] = $productionShift;
                
                // Create 1-3 downtime events per shift
                $downtimeCount = rand(1, 3);
                for ($d = 0; $d < $downtimeCount; $d++) {
                    $reasonCode = $reasonCodes[array_rand($reasonCodes)];
                    $durationMinutes = rand(5, 30);
                    
                    $dtStart = $shiftStart->copy()->addMinutes(rand(30, 300));
                    
                    DowntimeEvent::create([
                        'machine_id' => $machine->id,
                        'production_shift_id' => $productionShift->id,
                        'reason_code_id' => $reasonCode->id,
                        'start_time' => $dtStart,
                        'end_time' => $dtStart->copy()->addMinutes($durationMinutes),
                        'duration_seconds' => $durationMinutes * 60,
                        'comment' => 'Automated test data',
                    ]);
                }
                
                // Create 0-2 material loss events per shift
                $lossCount = rand(0, 2);
                for ($l = 0; $l < $lossCount; $l++) {
                    $lossCategory = $lossCategories[array_rand($lossCategories)];
                    $quantity = rand(5, 50);
                    
                    MaterialLoss::create([
                        'shift_id' => $productionShift->id,
                        'loss_category_id' => $lossCategory->id,
                        'product_id' => $product->id,
                        'machine_id' => $machine->id,
                        'recorded_by' => 1,
                        'quantity' => $quantity,
                        'unit' => $lossCategory->unit,
                        'reason' => 'Test data - automated seeder',
                        'notes' => 'Generated for testing purposes',
                        'cost_estimate' => $quantity * rand(2, 10),
                        'occurred_at' => $shiftStart->copy()->addMinutes(rand(60, 420)),
                    ]);
                }
                
                // Update shift metadata with material loss
                $materialLossUnits = MaterialLoss::where('shift_id', $productionShift->id)
                    ->whereHas('category', fn($q) => $q->where('affects_oee', true))
                    ->sum('quantity');
                    
                $materialLossCost = MaterialLoss::where('shift_id', $productionShift->id)
                    ->sum('cost_estimate');
                
                // Calculate quality score
                $totalUnits = $goodCount + $rejectCount + $materialLossUnits;
                $qualityScore = $totalUnits > 0 ? ($goodCount / $totalUnits) * 100 : 100;
                
                $productionShift->update([
                    'metadata' => array_merge($productionShift->metadata, [
                        'material_loss_units' => $materialLossUnits,
                        'material_loss_cost' => $materialLossCost,
                        'quality_score' => round($qualityScore, 2),
                    ])
                ]);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->command->newLine();
        $this->command->info("✓ Created " . count($productionShifts) . " production shifts with downtime and material loss events");

        // Step 9: Create Daily OEE Metrics
        $this->command->info("Generating daily OEE metrics...");
        
        // Group production shifts by date and machine
        foreach ($machines as $machine) {
            for ($day = 0; $day < 30; $day++) {
                $currentDate = $startDate->copy()->addDays($day);
                
                $dayShifts = ProductionShift::where('machine_id', $machine->id)
                    ->whereDate('started_at', $currentDate->toDateString())
                    ->get();
                
                if ($dayShifts->isEmpty()) continue;
                
                $totalGood = $dayShifts->sum(fn($s) => $s->metadata['good_count'] ?? 0);
                $totalReject = $dayShifts->sum(fn($s) => $s->metadata['reject_count'] ?? 0);
                $totalDowntime = $dayShifts->sum(fn($s) => ($s->metadata['downtime_minutes'] ?? 0) * 60);
                
                $totalMaterialLoss = MaterialLoss::whereIn('shift_id', $dayShifts->pluck('id'))
                    ->whereHas('category', fn($q) => $q->where('affects_oee', true))
                    ->sum('quantity');
                    
                $totalMaterialCost = MaterialLoss::whereIn('shift_id', $dayShifts->pluck('id'))
                    ->sum('cost_estimate');
                
                // Calculate OEE scores
                $totalElapsed = $dayShifts->sum(function($s) {
                    return $s->started_at->diffInSeconds($s->ended_at);
                });
                
                $runTime = max(0, $totalElapsed - $totalDowntime);
                $availability = $totalElapsed > 0 ? ($runTime / $totalElapsed) * 100 : 0;
                
                $idealRate = $dayShifts->avg(fn($s) => $s->metadata['ideal_rate'] ?? 100);
                $target = ($runTime / 3600) * $idealRate;
                $performance = $target > 0 ? (($totalGood + $totalReject) / $target) * 100 : 0;
                
                $totalUnits = $totalGood + $totalReject + $totalMaterialLoss;
                $quality = $totalUnits > 0 ? ($totalGood / $totalUnits) * 100 : 100;
                
                $oee = ($availability * $performance * $quality) / 10000;
                
                DailyOeeMetric::create([
                    'machine_id' => $machine->id,
                    'date' => $currentDate->toDateString(),
                    'oee_score' => min(round($oee, 1), 100),
                    'availability_score' => min(round($availability, 1), 100),
                    'performance_score' => min(round($performance, 1), 100),
                    'quality_score' => min(round($quality, 1), 100),
                    'total_good' => $totalGood,
                    'total_reject' => $totalReject,
                    'total_downtime' => $totalDowntime,
                    'total_material_loss' => $totalMaterialLoss,
                    'material_loss_cost' => $totalMaterialCost,
                ]);
            }
        }

        $this->command->info("✓ Generated daily OEE metrics for all machines");

        // Summary
        $this->command->newLine();
        $this->command->info("========================================");
        $this->command->info("DATABASE POPULATED SUCCESSFULLY");
        $this->command->info("========================================");
        $this->command->info("Organizations: 1");
        $this->command->info("Plants: " . count($plants));
        $this->command->info("Shifts: " . count($shifts));
        $this->command->info("Products: " . count($products));
        $this->command->info("Reason Codes: " . count($reasonCodes));
        $this->command->info("Material Loss Categories: " . count($lossCategories));
        $this->command->info("Machines: " . count($machines));
        $this->command->info("Production Shifts: " . count($productionShifts));
        $this->command->info("Downtime Events: " . DowntimeEvent::count());
        $this->command->info("Material Losses: " . MaterialLoss::count());
        $this->command->info("Daily OEE Metrics: " . DailyOeeMetric::count());
        $this->command->info("========================================");
    }
}
