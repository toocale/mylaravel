<?php

namespace App\Services;

use App\Models\Plant;
use App\Models\Shift;
use Carbon\Carbon;

class ShiftService
{
    /**
     * Get the active shift context (shift + date) for the given plant.
     */
    /**
     * Get the active shift context (shift + date) for the given machine (priority) or plant.
     */
    public function getCurrentShiftContext(Plant $plant, $machine = null)
    {
        return $this->getShiftContextForTime($plant, now(), $machine);
    }

    /**
     * Determine which shift covers the given timestamp and what its logical "production date" is.
     */
    public function getShiftContextForTime(Plant $plant, Carbon $time, $machine = null)
    {
        $timeStr = $time->format('H:i:s');
        
        // Priority 1: Machine specific shifts
        if ($machine && $machine->shifts->count() > 0) {
            $shifts = $machine->shifts;
        } else {
            // Priority 2: Plant wide shifts
            $shifts = $plant->shifts;
        }

        foreach ($shifts as $shift) {
            if ($this->isTimeInShift($timeStr, $shift->start_time, $shift->end_time)) {
                // Determine logical date
                // If the shift is overnight (Start > End) and the current time is before the End time (e.g. early morning),
                // then the shift actually started yesterday.
                $shiftDate = $time->toDateString();
                
                if ($shift->start_time > $shift->end_time && $timeStr < $shift->end_time) {
                    $shiftDate = $time->copy()->subDay()->toDateString();
                }

                return [
                    'shift' => $shift,
                    'date' => $shiftDate,
                ];
            }
        }

        return null;
    }

    private function isTimeInShift($current, $start, $end)
    {
        // Normal shift (e.g., 06:00 to 14:00)
        if ($start < $end) {
            return $current >= $start && $current < $end;
        } 
        // Overnight shift (e.g., 22:00 to 06:00)
        else {
            return $current >= $start || $current < $end;
        }
    }
}
