<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductionSimulatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $machines = \App\Models\Machine::all();
        if($machines->isEmpty()) return;

        $product = \App\Models\Product::first();
        $shift = \App\Models\Shift::first();
        $reasons = \App\Models\ReasonCode::all();

        // Simulate for a few machines
        foreach($machines->take(3) as $machine) {
            // 1. Create Production Logs
            \App\Models\ProductionLog::create([
                'machine_id' => $machine->id,
                'product_id' => $product->id ?? 1,
                'shift_id' => $shift->id ?? 1,
                'good_count' => rand(50, 200),
                'reject_count' => rand(0, 10),
                'start_time' => now(), 
            ]);

            // 2. Create Random Downtime
            if ($reasons->isNotEmpty() && rand(0, 1)) {
                $reason = $reasons->random();
                $duration = rand(300, 1800); // 5 to 30 mins
                $start = now()->subMinutes(rand(60, 300));
                
                \App\Models\DowntimeEvent::create([
                    'machine_id' => $machine->id,
                    'reason_code_id' => $reason->id,
                    'shift_id' => $shift->id ?? 1,
                    'start_time' => $start,
                    'end_time' => $start->copy()->addSeconds($duration),
                    'duration_seconds' => $duration,
                ]);
            }
        }
    }
}
