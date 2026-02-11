<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Organization
        $org = \App\Models\Organization::firstOrCreate([
            'code' => 'DAW001'
        ], [
            'name' => 'Dawaoee Manufacturing',
        ]);

        // 2. Plant
        $plant = $org->plants()->create([
            'name' => 'Main Factory',
            'location' => 'Dubai, UAE',
        ]);

        // 3. Shifts
        $shiftMorning = $plant->shifts()->create(['name' => 'Morning', 'start_time' => '06:00:00', 'end_time' => '14:00:00']);
        $shiftEvening = $plant->shifts()->create(['name' => 'Evening', 'start_time' => '14:00:00', 'end_time' => '22:00:00']);
        $shiftNight = $plant->shifts()->create(['name' => 'Night', 'start_time' => '22:00:00', 'end_time' => '06:00:00']);

        // 4. Products
        $p1 = $org->products()->create(['name' => 'Water 500ml', 'sku' => 'W500']);
        $p2 = $org->products()->create(['name' => 'Juice 1L', 'sku' => 'J1000']);

        // 5. Reason Codes
        $rcJam = $org->reasonCodes()->create(['code' => 'JAM', 'description' => 'Bottle Jam', 'category' => 'unplanned']);
        $rcMat = $org->reasonCodes()->create(['code' => 'NOMAT', 'description' => 'No Material', 'category' => 'unplanned']);
        $rcBreak = $org->reasonCodes()->create(['code' => 'BREAK', 'description' => 'Operator Break', 'category' => 'planned']);

        // 6. Lines & Machines
        $lines = ['Filling Line A', 'Packaging Line B'];
        
        foreach ($lines as $lineName) {
            $line = $plant->lines()->create(['name' => $lineName]);
            
            // Create 3 machines per line
            for ($i = 1; $i <= 3; $i++) {
                $machine = $line->machines()->create([
                    'name' => "Machine {$lineName} - {$i}",
                    'status' => 'running',
                    'default_ideal_rate' => 1440, // UPH (was 2.5s)
                ]);

                // Configs
                $machine->machineProductConfigs()->create(['product_id' => $p1->id, 'ideal_rate' => 1800]); // was 2.0s
                $machine->machineProductConfigs()->create(['product_id' => $p2->id, 'ideal_rate' => 1028.57]); // was 3.5s

                // Generate 30 days of metrics
                $startDate = now()->subDays(30);
                for ($day = 0; $day <= 30; $day++) {
                    $date = $startDate->copy()->addDays($day);
                    
                    // Random OEE simulation
                    $availability = rand(8000, 9800) / 100;
                    $performance = rand(8500, 9900) / 100;
                    $quality = rand(9500, 9999) / 100; // High quality usually
                    $oee = ($availability * $performance * $quality) / 10000;

                    $machine->dailymetrics()->create([
                        'date' => $date->format('Y-m-d'),
                        'availability_score' => $availability,
                        'performance_score' => $performance,
                        'quality_score' => $quality,
                        'oee_score' => $oee,
                        'total_good' => rand(5000, 10000),
                        'total_reject' => rand(10, 200),
                        'total_run_time' => 20000, // dummy
                        'total_planned_production_time' => 24000, // dummy
                        'total_downtime' => 4000, // dummy
                    ]);
                }
            }
        }
    }
}
