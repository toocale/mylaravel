<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Machine;
use App\Services\OeeCalculationService;
use Carbon\Carbon;

class CalculateDailyOee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oee:calculate-daily {--days=30 : Number of past days to recalculate} {--machine= : Specific machine ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate daily OEE metrics for machines';

    /**
     * Execute the console command.
     */
    public function handle(OeeCalculationService $service)
    {
        $days = (int) $this->option('days');
        $machineId = $this->option('machine');

        $this->info("Calculating OEE for the last {$days} days...");

        $query = Machine::query();
        if ($machineId) {
            $query->where('id', $machineId);
        }
        $machines = $query->get();

        $bar = $this->output->createProgressBar($machines->count() * $days);

        foreach ($machines as $machine) {
            for ($i = 0; $i < $days; $i++) {
                $date = Carbon::now()->subDays($i);
                $service->calculateForMachine($machine, $date);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('OEE calculation completed.');
    }
}
