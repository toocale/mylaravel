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

class YearlyTestDataSeeder extends Seeder
{
    private $organization;
    private $plants = [];
    private $shifts = [];
    private $products = [];
    private $reasonCodes = [];
    private $lossCategories = [];
    private $machines = [];

    public function run(): void
    {
        $this->command->info("========================================");
        $this->command->info("YEARLY TEST DATA SEEDER");
        $this->command->info("Generating 365 days of production data");
        $this->command->info("========================================");
        $this->command->newLine();

        // Step 1: Setup base data
        $this->setupOrganization();
        $this->setupPlants();
        $this->setupShifts();
        $this->setupProducts();
        $this->setupReasonCodes();
        $this->setupMaterialLossCategories();
        $this->setupMachines();

        // Step 2: Generate year's worth of production data
        $this->generateProductionData();

        // Step 3: Generate daily metrics
        $this->generateDailyMetrics();

        // Summary
        $this->displaySummary();
    }

    private function setupOrganization(): void
    {
        $this->organization = Organization::firstOrCreate(
            ['code' => 'VICOEE001'],
            ['name' => 'Vicoee Manufacturing']
        );
        $this->command->info("✓ Organization: {$this->organization->name}");
    }

    private function setupPlants(): void
    {
        $plantNames = [
            'Main Production Facility',
            'Assembly Plant Alpha',
            'Packaging Center Beta',
        ];

        foreach ($plantNames as $name) {
            $this->plants[] = Plant::firstOrCreate(
                ['name' => $name, 'organization_id' => $this->organization->id],
                ['organization_id' => $this->organization->id]
            );
        }
        $this->command->info("✓ Plants: " . count($this->plants));
    }

    private function setupShifts(): void
    {
        $shiftData = [
            ['name' => 'Morning Shift', 'type' => 'day', 'start_time' => '06:00:00', 'end_time' => '14:00:00'],
            ['name' => 'Afternoon Shift', 'type' => 'day', 'start_time' => '14:00:00', 'end_time' => '22:00:00'],
            ['name' => 'Night Shift', 'type' => 'night', 'start_time' => '22:00:00', 'end_time' => '06:00:00'],
        ];

        foreach ($this->plants as $plant) {
            foreach ($shiftData as $data) {
                $this->shifts[] = Shift::firstOrCreate(
                    ['name' => $data['name'], 'plant_id' => $plant->id],
                    array_merge($data, ['plant_id' => $plant->id])
                );
            }
        }
        $this->command->info("✓ Shifts: " . count($this->shifts));
    }

    private function setupProducts(): void
    {
        $productData = [
            ['name' => 'Widget Alpha', 'sku' => 'WGT-001'],
            ['name' => 'Widget Beta', 'sku' => 'WGT-002'],
            ['name' => 'Gadget Pro', 'sku' => 'GDT-001'],
            ['name' => 'Component X', 'sku' => 'CMP-001'],
            ['name' => 'Assembly Kit A', 'sku' => 'ASM-001'],
            ['name' => 'Circuit Board', 'sku' => 'PCB-001'],
            ['name' => 'Power Module', 'sku' => 'PWR-001'],
            ['name' => 'Sensor Unit', 'sku' => 'SNS-001'],
        ];

        foreach ($productData as $data) {
            $this->products[] = Product::firstOrCreate(
                ['name' => $data['name'], 'organization_id' => $this->organization->id],
                array_merge($data, ['organization_id' => $this->organization->id])
            );
        }
        $this->command->info("✓ Products: " . count($this->products));
    }

    private function setupReasonCodes(): void
    {
        $reasonData = [
            ['code' => 'BRK-001', 'description' => 'Scheduled Break', 'category' => 'planned'],
            ['code' => 'BRK-002', 'description' => 'Lunch Break', 'category' => 'planned'],
            ['code' => 'MAINT-001', 'description' => 'Preventive Maintenance', 'category' => 'planned'],
            ['code' => 'MAINT-002', 'description' => 'Routine Inspection', 'category' => 'planned'],
            ['code' => 'FAIL-001', 'description' => 'Equipment Failure', 'category' => 'unplanned'],
            ['code' => 'FAIL-002', 'description' => 'Electrical Failure', 'category' => 'unplanned'],
            ['code' => 'FAIL-003', 'description' => 'Mechanical Failure', 'category' => 'unplanned'],
            ['code' => 'JAM-001', 'description' => 'Material Jam', 'category' => 'unplanned'],
            ['code' => 'SETUP-001', 'description' => 'Product Changeover', 'category' => 'planned'],
            ['code' => 'SETUP-002', 'description' => 'Tooling Change', 'category' => 'planned'],
            ['code' => 'NOMAT-001', 'description' => 'No Material', 'category' => 'unplanned'],
            ['code' => 'NOMAT-002', 'description' => 'Material Quality Issue', 'category' => 'unplanned'],
            ['code' => 'QUAL-001', 'description' => 'Quality Hold', 'category' => 'unplanned'],
            ['code' => 'OTHER-001', 'description' => 'Other Reason', 'category' => 'unplanned'],
        ];

        foreach ($reasonData as $data) {
            $this->reasonCodes[] = ReasonCode::firstOrCreate(
                ['code' => $data['code'], 'organization_id' => $this->organization->id],
                array_merge($data, ['organization_id' => $this->organization->id])
            );
        }
        $this->command->info("✓ Reason Codes: " . count($this->reasonCodes));
    }

    private function setupMaterialLossCategories(): void
    {
        $lossCategoryData = [
            ['code' => 'PKG_WASTE', 'name' => 'Package Waste', 'loss_type' => 'packaging', 'affects_oee' => true, 'requires_reason' => true],
            ['code' => 'SPILL', 'name' => 'Spillage', 'loss_type' => 'raw_material', 'affects_oee' => true, 'requires_reason' => true],
            ['code' => 'SCRAP', 'name' => 'Production Scrap', 'loss_type' => 'other', 'affects_oee' => true, 'requires_reason' => false],
            ['code' => 'SAMPLE', 'name' => 'Quality Sampling', 'loss_type' => 'other', 'affects_oee' => false, 'requires_reason' => false],
            ['code' => 'DEFECT', 'name' => 'Defective Material', 'loss_type' => 'raw_material', 'affects_oee' => true, 'requires_reason' => true],
            ['code' => 'REWORK', 'name' => 'Rework Waste', 'loss_type' => 'packaging', 'affects_oee' => true, 'requires_reason' => true],
        ];

        foreach ($lossCategoryData as $data) {
            $this->lossCategories[] = MaterialLossCategory::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['active' => true, 'description' => ''])
            );
        }
        $this->command->info("✓ Material Loss Categories: " . count($this->lossCategories));
    }

    private function setupMachines(): void
    {
        $lineData = [
            'Main Production Facility' => [
                ['name' => 'Assembly Line 1', 'machines' => ['Machine A1', 'Machine A2', 'Machine A3']],
                ['name' => 'Assembly Line 2', 'machines' => ['Machine B1', 'Machine B2', 'Machine B3']],
                ['name' => 'Assembly Line 3', 'machines' => ['Machine C1', 'Machine C2']],
            ],
            'Assembly Plant Alpha' => [
                ['name' => 'Packaging Line 1', 'machines' => ['Packer 1', 'Packer 2', 'Labeler 1']],
                ['name' => 'Packaging Line 2', 'machines' => ['Packer 3', 'Labeler 2']],
            ],
            'Packaging Center Beta' => [
                ['name' => 'Final Assembly', 'machines' => ['Assembler 1', 'Assembler 2', 'Assembler 3']],
            ],
        ];

        foreach ($this->plants as $plant) {
            if (isset($lineData[$plant->name])) {
                foreach ($lineData[$plant->name] as $lineInfo) {
                    $line = Line::firstOrCreate(
                        ['name' => $lineInfo['name'], 'plant_id' => $plant->id],
                        ['plant_id' => $plant->id]
                    );

                    foreach ($lineInfo['machines'] as $machineName) {
                        $machine = Machine::firstOrCreate(
                            ['name' => $machineName, 'line_id' => $line->id],
                            [
                                'line_id' => $line->id,
                                'default_ideal_rate' => rand(60, 150),
                            ]
                        );

                        $this->machines[] = $machine;

                        // Assign products to machines with specific ideal rates
                        foreach ($this->products as $product) {
                            MachineProductConfig::firstOrCreate(
                                ['machine_id' => $machine->id, 'product_id' => $product->id],
                                ['ideal_rate' => rand(50, 130)]
                            );
                        }
                    }
                }
            }
        }
        $this->command->info("✓ Machines: " . count($this->machines));
    }

    private function generateProductionData(): void
    {
        $this->command->newLine();
        $this->command->info("Generating production shifts for 365 days...");
        
        // Start from exactly 1 year ago
        $startDate = Carbon::now()->subYear()->startOfDay();
        $endDate = Carbon::now();
        
        $totalDays = 365;
        $progressBar = $this->command->getOutput()->createProgressBar($totalDays);
        $progressBar->setFormat(' %current%/%max% days [%bar%] %percent:3s%% -- %message%');
        
        $shiftCount = 0;
        $batchData = [];
        
        for ($day = 0; $day < $totalDays; $day++) {
            $currentDate = $startDate->copy()->addDays($day);
            $isWeekend = $currentDate->isWeekend();
            
            $progressBar->setMessage("Processing " . $currentDate->format('Y-m-d'));
            
            // Generate shifts for each machine
            foreach ($this->machines as $machine) {
                // Weekend: 1-2 shifts, Weekday: 2-3 shifts
                $shiftsPerMachine = $isWeekend ? rand(1, 2) : rand(2, 3);
                
                // Randomly skip some days for variety (simulate maintenance days, etc.)
                if (rand(1, 100) <= 10) { // 10% chance to skip
                    continue;
                }
                
                $usedShiftIds = [];
                
                for ($i = 0; $i < $shiftsPerMachine; $i++) {
                    // Get a unique shift for this day/machine
                    $availableShifts = array_filter($this->shifts, fn($s) => !in_array($s->id, $usedShiftIds));
                    if (empty($availableShifts)) break;
                    
                    $shift = $availableShifts[array_rand($availableShifts)];
                    $usedShiftIds[] = $shift->id;
                    
                    $product = $this->products[array_rand($this->products)];
                    
                    // Create shift with realistic timing
                    $shiftStart = $currentDate->copy()->setTimeFromTimeString($shift->start_time);
                    $shiftEnd = $shiftStart->copy()->addHours(8);
                    
                    // Skip future shifts
                    if ($shiftStart->isFuture()) continue;
                    if ($shiftEnd->isFuture()) {
                        $shiftEnd = Carbon::now();
                    }
                    
                    // Generate realistic production numbers with seasonal variation
                    $seasonMultiplier = $this->getSeasonalMultiplier($currentDate);
                    $baseGoodCount = rand(450, 900);
                    $goodCount = (int)($baseGoodCount * $seasonMultiplier);
                    $rejectCount = rand(5, max(6, (int)($goodCount * 0.05))); // 0-5% rejection
                    $idealRate = rand(60, 130);
                    $downtimeMinutes = rand(10, 90);
                    
                    // Create the production shift
                    $productionShift = ProductionShift::create([
                        'machine_id' => $machine->id,
                        'user_id' => 1,
                        'shift_id' => $shift->id,
                        'product_id' => $product->id,
                        'started_at' => $shiftStart,
                        'ended_at' => $shiftEnd,
                        'status' => 'completed',
                        'user_group' => ['Operator', 'Technician', 'Lead'][array_rand(['Operator', 'Technician', 'Lead'])],
                        'good_count' => $goodCount,
                        'reject_count' => $rejectCount,
                        'total_count' => $goodCount + $rejectCount,
                        'batch_number' => 'B-' . $currentDate->format('ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                        'metadata' => [
                            'good_count' => $goodCount,
                            'reject_count' => $rejectCount,
                            'total_output' => $goodCount + $rejectCount,
                            'target_output' => rand(800, 1100),
                            'ideal_rate' => $idealRate,
                            'downtime_minutes' => $downtimeMinutes,
                        ]
                    ]);
                    
                    $shiftCount++;
                    
                    // Create downtime events (1-4 per shift)
                    $this->createDowntimeEvents($productionShift, $shiftStart, $machine->id);
                    
                    // Create material losses (0-3 per shift)
                    $this->createMaterialLosses($productionShift, $shiftStart, $product->id, $machine->id);
                }
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->command->newLine();
        $this->command->info("✓ Created {$shiftCount} production shifts");
    }

    private function getSeasonalMultiplier(Carbon $date): float
    {
        $month = $date->month;
        
        // Simulate seasonal patterns
        $seasonalPatterns = [
            1 => 0.85,   // January - post-holiday slowdown
            2 => 0.90,
            3 => 0.95,
            4 => 1.00,   // Spring - normal
            5 => 1.05,
            6 => 1.10,   // Summer peak
            7 => 1.08,
            8 => 1.05,
            9 => 1.00,   // Fall - normal
            10 => 0.95,
            11 => 0.90,  // Pre-holiday
            12 => 0.80,  // Holiday slowdown
        ];
        
        $base = $seasonalPatterns[$month] ?? 1.0;
        
        // Add some random daily variation (±10%)
        return $base * (0.90 + (rand(0, 20) / 100));
    }

    private function createDowntimeEvents(ProductionShift $shift, Carbon $shiftStart, int $machineId): void
    {
        $downtimeCount = rand(1, 4);
        $usedTimes = [];
        
        for ($d = 0; $d < $downtimeCount; $d++) {
            $reasonCode = $this->reasonCodes[array_rand($this->reasonCodes)];
            
            // Vary duration based on reason type
            $durationMinutes = match($reasonCode->category) {
                'planned' => rand(10, 30),
                'unplanned' => rand(5, 60),
                default => rand(5, 30),
            };
            
            // Find non-overlapping time slot
            $maxAttempts = 5;
            $attempt = 0;
            $dtStart = null;
            
            do {
                $minuteOffset = rand(20, 420); // Within 7 hours of shift
                $dtStart = $shiftStart->copy()->addMinutes($minuteOffset);
                $attempt++;
            } while ($attempt < $maxAttempts && $this->overlaps($dtStart, $durationMinutes, $usedTimes));
            
            if ($dtStart) {
                $usedTimes[] = ['start' => $dtStart, 'duration' => $durationMinutes];
                
                DowntimeEvent::create([
                    'machine_id' => $machineId,
                    'production_shift_id' => $shift->id,
                    'reason_code_id' => $reasonCode->id,
                    'start_time' => $dtStart,
                    'end_time' => $dtStart->copy()->addMinutes($durationMinutes),
                    'duration_seconds' => $durationMinutes * 60,
                    'comment' => $this->generateDowntimeComment($reasonCode->description),
                ]);
            }
        }
    }

    private function overlaps(Carbon $start, int $duration, array $existing): bool
    {
        $end = $start->copy()->addMinutes($duration);
        
        foreach ($existing as $slot) {
            $slotEnd = $slot['start']->copy()->addMinutes($slot['duration']);
            if (!($end->lte($slot['start']) || $start->gte($slotEnd))) {
                return true;
            }
        }
        
        return false;
    }

    private function generateDowntimeComment(string $reason): string
    {
        $comments = [
            'Scheduled Break' => ['15-minute break', 'Regular break period', 'Team break'],
            'Lunch Break' => ['Lunch period', 'Meal break', '30-minute lunch'],
            'Preventive Maintenance' => ['Routine PM', 'Scheduled maintenance', 'Lubrication and check'],
            'Routine Inspection' => ['Quality inspection', 'Safety check', 'Equipment inspection'],
            'Equipment Failure' => ['Motor issue', 'Sensor malfunction', 'Control system error'],
            'Electrical Failure' => ['Power supply issue', 'Wiring problem', 'Circuit breaker trip'],
            'Mechanical Failure' => ['Belt replacement', 'Bearing issue', 'Gear malfunction'],
            'Material Jam' => ['Cleared jam', 'Material blockage removed', 'Feed system cleared'],
            'Product Changeover' => ['Setup for new product', 'Tooling change completed', 'Dies changed'],
            'Tooling Change' => ['Tool replacement', 'Blade change', 'Fixture swap'],
            'No Material' => ['Waiting for materials', 'Supply delay', 'Material shortage'],
            'Material Quality Issue' => ['Material rejected', 'Quality hold', 'Supplier issue'],
            'Quality Hold' => ['Product inspection', 'Quality verification', 'Hold for testing'],
            'Other Reason' => ['Miscellaneous issue', 'Operator delay', 'Coordination required'],
        ];
        
        $options = $comments[$reason] ?? ['Automated log entry'];
        return $options[array_rand($options)];
    }

    private function createMaterialLosses(ProductionShift $shift, Carbon $shiftStart, int $productId, int $machineId): void
    {
        $lossCount = rand(0, 3);
        
        for ($l = 0; $l < $lossCount; $l++) {
            $lossCategory = $this->lossCategories[array_rand($this->lossCategories)];
            
            // Vary quantity based on category
            $quantity = match($lossCategory->code) {
                'PKG_WASTE' => rand(2, 15),
                'SPILL' => rand(1, 8),
                'SAMPLE' => rand(3, 10),
                default => rand(5, 50),
            };
            
            // Determine unit based on loss type
            $unit = match($lossCategory->loss_type) {
                'raw_material' => ['kg', 'liters'][rand(0, 1)],
                'packaging' => 'units',
                default => 'units',
            };
            
            MaterialLoss::create([
                'shift_id' => $shift->id,
                'loss_category_id' => $lossCategory->id,
                'loss_type' => $lossCategory->loss_type,
                'product_id' => $productId,
                'machine_id' => $machineId,
                'recorded_by' => 1,
                'quantity' => $quantity,
                'unit' => $unit,
                'reason' => $this->generateLossReason($lossCategory->name),
                'notes' => 'Auto-generated test data',
                'cost_estimate' => $quantity * rand(5, 25),
                'occurred_at' => $shiftStart->copy()->addMinutes(rand(30, 420)),
            ]);
        }
    }

    private function generateLossReason(string $category): string
    {
        $reasons = [
            'Package Waste' => ['Damaged packaging', 'Incorrect seal', 'Label mismatch', 'Torn material'],
            'Spillage' => ['Container leak', 'Overflow', 'Pump failure', 'Hose disconnected'],
            'Production Scrap' => ['Dimension out of spec', 'Surface defect', 'Assembly error', 'Test failure'],
            'Quality Sampling' => ['QC testing', 'Destructive testing', 'Customer sample', 'Lab analysis'],
            'Defective Material' => ['Incoming defect', 'Material flaw', 'Wrong specification', 'Contamination'],
            'Rework Waste' => ['Failed rework', 'Multiple defects', 'Not recoverable', 'Time constraints'],
        ];
        
        $options = $reasons[$category] ?? ['General loss'];
        return $options[array_rand($options)];
    }

    private function generateDailyMetrics(): void
    {
        $this->command->newLine();
        $this->command->info("Generating daily OEE metrics...");
        
        $startDate = Carbon::now()->subYear()->startOfDay();
        $totalDays = 365;
        
        $progressBar = $this->command->getOutput()->createProgressBar($totalDays);
        $metricsCreated = 0;
        
        foreach ($this->machines as $machine) {
            for ($day = 0; $day < $totalDays; $day++) {
                $currentDate = $startDate->copy()->addDays($day);
                
                $dayShifts = ProductionShift::where('machine_id', $machine->id)
                    ->whereDate('started_at', $currentDate->toDateString())
                    ->get();
                
                if ($dayShifts->isEmpty()) continue;
                
                $totalGood = $dayShifts->sum(fn($s) => $s->metadata['good_count'] ?? $s->good_count ?? 0);
                $totalReject = $dayShifts->sum(fn($s) => $s->metadata['reject_count'] ?? $s->reject_count ?? 0);
                $totalDowntime = $dayShifts->sum(fn($s) => ($s->metadata['downtime_minutes'] ?? 0) * 60);
                
                $totalMaterialLoss = MaterialLoss::whereIn('shift_id', $dayShifts->pluck('id'))
                    ->whereHas('category', fn($q) => $q->where('affects_oee', true))
                    ->sum('quantity');
                    
                $totalMaterialCost = MaterialLoss::whereIn('shift_id', $dayShifts->pluck('id'))
                    ->sum('cost_estimate');
                
                // Calculate OEE scores
                $totalElapsed = $dayShifts->sum(function($s) {
                    if (!$s->started_at || !$s->ended_at) return 0;
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
                
                DailyOeeMetric::updateOrCreate(
                    [
                        'machine_id' => $machine->id,
                        'date' => $currentDate->toDateString(),
                    ],
                    [
                        'oee_score' => min(round($oee, 1), 100),
                        'availability_score' => min(round($availability, 1), 100),
                        'performance_score' => min(round($performance, 1), 100),
                        'quality_score' => min(round($quality, 1), 100),
                        'total_good' => $totalGood,
                        'total_reject' => $totalReject,
                        'total_downtime' => $totalDowntime,
                        'total_material_loss' => $totalMaterialLoss,
                        'material_loss_cost' => $totalMaterialCost,
                    ]
                );
                
                $metricsCreated++;
            }
            
            $progressBar->advance(1);
        }
        
        $progressBar->finish();
        $this->command->newLine();
        $this->command->info("✓ Created/Updated {$metricsCreated} daily OEE metrics");
    }

    private function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info("========================================");
        $this->command->info("YEARLY TEST DATA SEEDING COMPLETE");
        $this->command->info("========================================");
        $this->command->info("Organizations: 1");
        $this->command->info("Plants: " . count($this->plants));
        $this->command->info("Shifts: " . count($this->shifts));
        $this->command->info("Products: " . count($this->products));
        $this->command->info("Reason Codes: " . count($this->reasonCodes));
        $this->command->info("Material Loss Categories: " . count($this->lossCategories));
        $this->command->info("Machines: " . count($this->machines));
        $this->command->info("Production Shifts: " . ProductionShift::count());
        $this->command->info("Downtime Events: " . DowntimeEvent::count());
        $this->command->info("Material Losses: " . MaterialLoss::count());
        $this->command->info("Daily OEE Metrics: " . DailyOeeMetric::count());
        $this->command->info("========================================");
        $this->command->newLine();
        $this->command->info("🎉 Database populated with a full year of realistic test data!");
    }
}
