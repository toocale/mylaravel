<?php

namespace App\Observers;

use App\Models\DowntimeEvent;
use App\Models\Machine;
use App\Services\OeeCalculationService;

class DowntimeEventObserver
{
    protected $service;

    public function __construct(OeeCalculationService $service)
    {
        $this->service = $service;
    }

    public function created(DowntimeEvent $downtimeEvent): void
    {
        $this->calculate($downtimeEvent);
    }

    public function updated(DowntimeEvent $downtimeEvent): void
    {
        $this->calculate($downtimeEvent);
    }

    public function deleted(DowntimeEvent $downtimeEvent): void
    {
        $this->calculate($downtimeEvent);
    }

    protected function calculate(DowntimeEvent $event)
    {
        $machine = Machine::find($event->machine_id);
        if ($machine) {
            $this->service->calculateForMachine($machine, $event->start_time);
        }
    }
}
