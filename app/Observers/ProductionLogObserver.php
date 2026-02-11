<?php

namespace App\Observers;

use App\Models\Machine;
use App\Models\ProductionLog;
use App\Services\OeeCalculationService;

class ProductionLogObserver
{
    protected $service;

    public function __construct(OeeCalculationService $service)
    {
        $this->service = $service;
    }

    public function created(ProductionLog $productionLog): void
    {
        $this->calculate($productionLog);
    }

    public function updated(ProductionLog $productionLog): void
    {
        $this->calculate($productionLog);
    }

    public function deleted(ProductionLog $productionLog): void
    {
        $this->calculate($productionLog);
    }

    protected function calculate(ProductionLog $log)
    {
        $machine = Machine::find($log->machine_id);
        if ($machine) {
            $this->service->calculateForMachine($machine, $log->start_time);
        }
    }
}
