<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductionShift;
use App\Models\DowntimeEvent;
use Carbon\Carbon;

class Mine1ShiftSeeder extends Seeder
{
    /**
     * Seed production shifts for mine1 (machine_id = 17) to match daily_oee_metrics
     * 
     * Daily metrics to match:
     * - Dec 16: OEE 86.72%, Avail 94.83%, Perf 95%, Qual 96.26%
     * - Dec 17: OEE 76.2%, Avail 83.5%, Perf 91.5%, Qual 99.6%
     * - Dec 18: OEE 78.5%, Avail 85.2%, Perf 92.1%, Qual 99.8%
     */
    public function run(): void
    {
        // Machine ID for mine1
        $machineId = 17;
        
        // Get the machine to find product and shift info
        $machine = \App\Models\Machine::find($machineId);
        $productConfig = $machine->machineProductConfigs()->first();
        $productId = $productConfig ? $productConfig->product_id : null;
        $idealRate = $productConfig ? $productConfig->ideal_rate : 100; // units per hour
        
        // Get first shift assigned to this machine (or use null for ad-hoc)
        $shiftId = $machine->shifts()->first()?->id;
        
        // Dec 16: OEE 86.72%, Avail 94.83%, Perf 95%, Qual 96.26%
        $this->createShift(
            machineId: $machineId,
            date: '2025-12-16',
            startTime: '08:00:00',
            duration: 480, // 8 hours in minutes
            availability: 94.83,
            performance: 95.0,
            quality: 96.26,
            idealRate: $idealRate,
            productId: $productId,
            shiftId: $shiftId
        );
        
        // Dec 17: OEE 76.2%, Avail 83.5%, Perf 91.5%, Qual 99.6%
        $this->createShift(
            machineId: $machineId,
            date: '2025-12-17',
            startTime: '08:00:00',
            duration: 480,
            availability: 83.5,
            performance: 91.5,
            quality: 99.6,
            idealRate: $idealRate,
            productId: $productId,
            shiftId: $shiftId
        );
        
        // Dec 18: OEE 78.5%, Avail 85.2%, Perf 92.1%, Qual 99.8%
        $this->createShift(
            machineId: $machineId,
            date: '2025-12-18',
            startTime: '08:00:00',
            duration: 480,
            availability: 85.2,
            performance: 92.1,
            quality: 99.8,
            idealRate: $idealRate,
            productId: $productId,
            shiftId: $shiftId
        );
        
        $this->command->info('Created 3 production shifts for mine1 to match daily_oee_metrics');
    }
    
    private function createShift(
        int $machineId,
        string $date,
        string $startTime,
        int $duration,
        float $availability,
        float $performance,
        float $quality,
        float $idealRate,
        ?int $productId,
        ?int $shiftId
    ): void {
        $startedAt = Carbon::parse("{$date} {$startTime}");
        $endedAt = $startedAt->copy()->addMinutes($duration);
        
        // Calculate downtime from availability
        // Availability = (Total Time - Downtime) / Total Time
        // Downtime = Total Time × (1 - Availability%)
        $totalSeconds = $duration * 60;
        $downtimeSeconds = $totalSeconds * (1 - ($availability / 100));
        $runTimeHours = ($totalSeconds - $downtimeSeconds) / 3600;
        
        // Calculate production from performance
        // Performance = Actual Output / Target Output
        // Target Output = Runtime (hours) × Ideal Rate
        $targetOutput = $runTimeHours * $idealRate;
        $actualOutput = $targetOutput * ($performance / 100);
        
        // Calculate good/reject from quality
        // Quality = Good / Total
        // Good = Total × Quality%
        $totalUnits = round($actualOutput);
        $goodUnits = round($totalUnits * ($quality / 100));
        $rejectUnits = $totalUnits - $goodUnits;
        
        // Create the shift
        $shift = ProductionShift::create([
            'machine_id' => $machineId,
            'product_id' => $productId,
            'shift_id' => $shiftId,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'user_id' => 1, // Assume admin user
            'status' => 'completed',
            'user_group' => 'Production',
            'metadata' => [
                'good_count' => $goodUnits,
                'reject_count' => $rejectUnits,
                'ideal_rate' => $idealRate,
                'target_output' => round($targetOutput),
                'downtime_minutes' => round($downtimeSeconds / 60, 2),
                'comment' => 'Generated to match daily_oee_metrics'
            ]
        ]);
        
        // NOTE: Skipping downtime event creation for now - can be added manually if needed
        // The metadata includes downtime_minutes for reference
        /*
        // Create downtime events if there was downtime
        if ($downtimeSeconds > 60) {
            // Get any reason code (just for data completeness)
            $reasonCode = \App\Models\ReasonCode::first();
            
            if ($reasonCode) {
                // Create a single downtime event for the calculated downtime
                $downtimeStart = $startedAt->copy()->addMinutes(rand(60, 120));
                DowntimeEvent::create([
                    'production_shift_id' => $shift->id,
                    'reason_code_id' => $reasonCode->id,
                    'started_at' => $downtimeStart,
                    'ended_at' => $downtimeStart->copy()->addSeconds($downtimeSeconds),
                    'duration_seconds' => round($downtimeSeconds),
                    'comment' => 'Downtime to achieve ' . round($availability, 1) . '% availability'
                ]);
            }
        }
        */
        
        $this->command->info("  Created shift for {$date}: Good={$goodUnits}, Reject={$rejectUnits}, Downtime=" . round($downtimeSeconds/60, 1) . "min");
    }
}
