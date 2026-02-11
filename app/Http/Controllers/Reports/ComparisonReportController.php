<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\DailyOeeMetric;
use App\Models\DowntimeEvent;
use App\Models\Machine;
use App\Models\Plant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ComparisonReportController extends Controller
{
    /**
     * Display comparison page
     */
    public function index(Request $request)
    {
        $plants = Plant::with(['lines.machines'])->get();
        $shifts = \App\Models\Shift::select('id', 'name', 'type')->get();
        
        return Inertia::render('Reports/Comparison', [
            'plants' => $plants,
            'shifts' => $shifts,
        ]);
    }

    /**
     * Compare two time periods
     */
    public function comparePeriods(Request $request)
    {
        $request->validate([
            'period1_from' => 'required|date',
            'period1_to' => 'required|date|after_or_equal:period1_from',
            'period2_from' => 'required|date',
            'period2_to' => 'required|date|after_or_equal:period2_from',
            'machine_id' => 'nullable|exists:machines,id',
            'line_id' => 'nullable|exists:lines,id',
            'plant_id' => 'nullable|exists:plants,id',
        ]);

        $period1 = $this->getPeriodMetrics(
            $request->period1_from,
            $request->period1_to,
            $request->machine_id,
            $request->line_id,
            $request->plant_id
        );

        $period2 = $this->getPeriodMetrics(
            $request->period2_from,
            $request->period2_to,
            $request->machine_id,
            $request->line_id,
            $request->plant_id
        );

        $variance = $this->calculateVariance($period1['metrics'], $period2['metrics']);

        return response()->json([
            'comparison_type' => 'period',
            'period1' => [
                'label' => $request->input('period1_label', 'Period 1'),
                'date_from' => $request->period1_from,
                'date_to' => $request->period1_to,
                'metrics' => $period1['metrics'],
                'daily_data' => $period1['daily'],
            ],
            'period2' => [
                'label' => $request->input('period2_label', 'Period 2'),
                'date_from' => $request->period2_from,
                'date_to' => $request->period2_to,
                'metrics' => $period2['metrics'],
                'daily_data' => $period2['daily'],
            ],
            'variance' => $variance,
        ]);
    }

    /**
     * Compare multiple machines
     */
    public function compareMachines(Request $request)
    {
        $request->validate([
            'machine_ids' => 'required|array|min:2|max:10',
            'machine_ids.*' => 'exists:machines,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        $machines = [];
        $allMetrics = [];

        foreach ($request->machine_ids as $machineId) {
            $data = $this->getPeriodMetrics(
                $request->date_from,
                $request->date_to,
                $machineId,
                null,
                null,
                $request->shift_id
            );

            $machine = Machine::with('line.plant')->find($machineId);
            
            $machines[] = [
                'id' => $machineId,
                'name' => $machine->name,
                'line' => $machine->line->name,
                'plant' => $machine->line->plant->name,
                'metrics' => $data['metrics'],
            ];

            $allMetrics[] = $data['metrics'];
        }

        // Calculate group averages
        $groupAverage = $this->calculateGroupAverage($allMetrics);

        // Add variance from average to each machine
        foreach ($machines as &$machine) {
            $machine['variance_from_avg'] = $this->calculateVariance($machine['metrics'], $groupAverage);
        }

        // Rankings
        $rankings = $this->calculateRankings($machines);

        return response()->json([
            'comparison_type' => 'machine',
            'period' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
            'machines' => $machines,
            'group_average' => $groupAverage,
            'rankings' => $rankings,
        ]);
    }

    /**
     * Compare shifts
     */
    public function compareShifts(Request $request)
    {
        $request->validate([
            'shift_ids' => 'required|array|min:2',
            'shift_ids.*' => 'exists:shifts,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'machine_id' => 'nullable|exists:machines,id',
        ]);

        $shifts = [];

        foreach ($request->shift_ids as $shiftId) {
            $data = $this->getPeriodMetrics(
                $request->date_from,
                $request->date_to,
                $request->machine_id,
                null,
                null,
                $shiftId
            );

            $shift = \App\Models\Shift::find($shiftId);
            
            $shifts[] = [
                'id' => $shiftId,
                'name' => $shift->name,
                'type' => $shift->type,
                'metrics' => $data['metrics'],
            ];
        }

        // Calculate variance between shifts
        $variances = [];
        if (count($shifts) >= 2) {
            $variances = $this->calculateVariance($shifts[0]['metrics'], $shifts[1]['metrics']);
        }

        return response()->json([
            'comparison_type' => 'shift',
            'period' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
            'shifts' => $shifts,
            'variance' => $variances,
        ]);
    }

    /**
     * Get period metrics
     */
    private function getPeriodMetrics($dateFrom, $dateTo, $machineId = null, $lineId = null, $plantId = null, $shiftId = null)
    {
        $query = DailyOeeMetric::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->with(['machine.line.plant']);

        if ($machineId) {
            $query->where('machine_id', $machineId);
        } elseif ($lineId) {
            $query->whereHas('machine', fn($q) => $q->where('line_id', $lineId));
        } elseif ($plantId) {
            $query->whereHas('machine.line', fn($q) => $q->where('plant_id', $plantId));
        }

        if ($shiftId) {
            $query->where('shift_id', $shiftId);
        }

        $metrics = $query->get();
        $daily = $query->orderBy('date')->get();

        return [
            'metrics' => [
                'avg_oee' => round($metrics->avg('oee'), 2),
                'avg_availability' => round($metrics->avg('availability'), 2),
                'avg_performance' => round($metrics->avg('performance'), 2),
                'avg_quality' => round($metrics->avg('quality'), 2),
                'total_units' => $metrics->sum('total_count'),
                'good_units' => $metrics->sum('good_count'),
                'reject_units' => $metrics->sum('reject_count'),
                'reject_rate' => $metrics->sum('total_count') > 0 
                    ? round(($metrics->sum('reject_count') / $metrics->sum('total_count')) * 100, 2) 
                    : 0,
                'downtime_hours' => round($metrics->sum('downtime_minutes') / 60, 2),
            ],
            'daily' => $daily->map(fn($m) => [
                'date' => $m->date,
                'oee' => $m->oee,
                'availability' => $m->availability,
                'performance' => $m->performance,
                'quality' => $m->quality,
            ]),
        ];
    }

    /**
     * Calculate variance between two metric sets
     */
    private function calculateVariance($metrics1, $metrics2)
    {
        $variance = [];
        
        foreach (['avg_oee', 'avg_availability', 'avg_performance', 'avg_quality', 'reject_rate'] as $key) {
            $val1 = $metrics1[$key] ?? 0;
            $val2 = $metrics2[$key] ?? 0;
            $diff = $val1 - $val2;
            
            $variance[$key] = [
                'value' => round($diff, 2),
                'percentage' => $val2 != 0 ? round(($diff / $val2) * 100, 2) : 0,
                'trend' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'stable'),
            ];
        }

        foreach (['total_units', 'good_units', 'reject_units', 'downtime_hours'] as $key) {
            $val1 = $metrics1[$key] ?? 0;
            $val2 = $metrics2[$key] ?? 0;
            $diff = $val1 - $val2;
            
            $variance[$key] = [
                'value' => round($diff, 2),
                'trend' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'stable'),
            ];
        }

        return $variance;
    }

    /**
     * Calculate group average from multiple metric sets
     */
    private function calculateGroupAverage($allMetrics)
    {
        $count = count($allMetrics);
        if ($count === 0) return [];

        $average = [];
        $keys = array_keys($allMetrics[0]);

        foreach ($keys as $key) {
            $sum = array_sum(array_column($allMetrics, $key));
            $average[$key] = round($sum / $count, 2);
        }

        return $average;
    }

    /**
     * Calculate rankings
     */
    private function calculateRankings($machines)
    {
        $rankings = [
            'best_oee' => null,
            'worst_oee' => null,
            'best_availability' => null,
            'worst_availability' => null,
        ];

        if (empty($machines)) return $rankings;

        // Find best and worst OEE
        $oeeValues = array_column(array_column($machines, 'metrics'), 'avg_oee');
        $bestOeeIndex = array_search(max($oeeValues), $oeeValues);
        $worstOeeIndex = array_search(min($oeeValues), $oeeValues);

        $rankings['best_oee'] = [
            'machine_id' => $machines[$bestOeeIndex]['id'],
            'machine_name' => $machines[$bestOeeIndex]['name'],
            'value' => $oeeValues[$bestOeeIndex],
        ];

        $rankings['worst_oee'] = [
            'machine_id' => $machines[$worstOeeIndex]['id'],
            'machine_name' => $machines[$worstOeeIndex]['name'],
            'value' => $oeeValues[$worstOeeIndex],
        ];

        return $rankings;
    }
}
