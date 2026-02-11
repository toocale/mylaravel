<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductionShift;
use Carbon\Carbon;

class TestShiftSeeder extends Seeder
{
    public function run()
    {
        echo "Creating test shifts for Main Factory...\n";
        
        $created = 0;
        
        // Create shifts for the past 10 days (excluding weekends)
        for ($i = 10; $i >= 1; $i--) {
            $date = Carbon::now()->subDays($i);
            
            // Skip weekends
            if ($date->dayOfWeek == 0 || $date->dayOfWeek == 6) {
                continue;
            }
            
            // Create shift for machine 11
            ProductionShift::create([
                'machine_id' => 11,
                'user_id' => 1,
                'product_id' => null,
                'shift_id' => null,
                'user_group' => 'Test Operator',
                'started_at' => $date->copy()->setTime(8, 0, 0),
                'ended_at' => $date->copy()->setTime(16, 0, 0),
                'status' => 'completed',
                'metadata' => [
                    'good_count' => rand(900, 1100),
                    'reject_count' => rand(10, 30),
                    'ideal_rate' => 150,
                    'downtime_minutes' => rand(15, 45),
                ]
            ]);
            
            $created++;
        }
        
        echo "Created {$created} test shifts successfully!\n";
    }
}
