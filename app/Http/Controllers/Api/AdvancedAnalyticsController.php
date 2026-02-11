<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductionShift;
use App\Models\DowntimeEvent;
use Illuminate\Support\Facades\DB;
use App\Models\SiteSetting;

class AdvancedAnalyticsController extends Controller
{
    /**
     * Get Loss Analysis (Waterfall Data)
     * Total Time -> Planned Downtime -> Unplanned Downtime -> Performance Loss -> Quality Loss -> Fully Productive
     */
    public function lossAnalysis(Request $request)
    {
        $machineId = $request->input('machine_id');
        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        
        // 1. Total Calendar Time (24/7 or Shift Based)
        // Ideally based on Shift Schedules, but for simplicity we use 24h * Days if no shifts found
        // OR better: Sum of all ProductionShift durations in the period
        
        $shifts = ProductionShift::query()
            ->where('status', 'completed')
            ->whereBetween('started_at', [$dateFrom, $dateTo . ' 23:59:59']);

        if ($machineId) {
            $shifts->where('machine_id', $machineId);
        }

        $shifts = $shifts->get();
        
        // --- Aggregates ---
        $totalShiftTime = 0; // Total Scheduled Time
        $totalRunTime = 0;
        $totalGood = 0;
        $totalReject = 0;
        $totalStandardTime = 0;
        
        foreach ($shifts as $shift) {
            $start = $shift->started_at;
            $end = $shift->ended_at ?? now();
            $duration = $start->diffInSeconds($end);
            $totalShiftTime += $duration;
            
            $good = (int)($shift->good_count ?? $shift->metadata['good_count'] ?? 0);
            $reject = (int)($shift->reject_count ?? $shift->metadata['reject_count'] ?? 0);
            $runTime = $shift->duration_seconds ?? ($duration); // Usually run_time is calculated
             
            // Re-calculate run time properly if not stored
            // We need downtime for THIS shift
            // Simplified: Run Time is passed from OEE service usually, but here we reconstruct
        }

        // Let's use a more direct approach: Aggregate from DailyOeeMetric if available? 
        // No, metrics might not separate losses explicitly enough for waterfall.
        // Let's reconstruct from basic blocks.
        
        // A. Total Time (Scheduled)
        // If we filter by 'shifts', Total Time = Sum of Shift Durations
        if ($shifts->isEmpty()) {
            return response()->json(['error' => 'No data for period'], 200);
        }

        // B. Downtime (Availability Loss)
        $shiftIds = $shifts->pluck('id');
        
        $downtimeEvents = DowntimeEvent::whereIn('production_shift_id', $shiftIds)->with('reasonCode')->get();
        
        $plannedDowntime = 0;
        $unplannedDowntime = 0;
        
        foreach ($downtimeEvents as $dt) {
            if ($dt->reasonCode && $dt->reasonCode->category === 'planned') {
                $plannedDowntime += $dt->duration_seconds;
            } else {
                $unplannedDowntime += $dt->duration_seconds;
            }
        }

        // C. Run Time
        $runTime = $totalShiftTime - $plannedDowntime - $unplannedDowntime;
        if ($runTime < 0) $runTime = 0;

        // D. Performance Loss & Quality Loss
        // We need Standard Cycles
        $performanceLossTime = 0;
        $qualityLossTime = 0;
        $fullyProductiveTime = 0;
        
        // Summing up counts
        foreach ($shifts as $shift) {
            $meta = $shift->metadata ?? [];
            $good = (int)($shift->good_count ?? $meta['good_count'] ?? 0);
            $reject = (int)($shift->reject_count ?? $meta['reject_count'] ?? 0);
            
            // Ideal Cycle Time
            $idealRate = $meta['ideal_rate'] ?? 0; // Units per Hour
            $ict = ($idealRate > 0) ? (3600.0 / $idealRate) : 0; // Seconds per Unit
            
            if ($ict > 0) {
                // Time needed to produce ALL units (Good + Reject) perfectly
                $timeToProduceAll = ($good + $reject) * $ict;
                
                // Time needed to produce ONLY GOOD units perfectly
                $timeToProduceGood = $good * $ict;
                
                // Performance Loss = Run Time - (Time to Produce All)
                // (Time lost due to running slower than ideal)
                // Note: If running faster, this becomes negative, meaning 0 loss.
                $pLoss = ($shift->duration_seconds ?? ($shift->started_at->diffInSeconds($shift->ended_at) - 0)) - $downtimeEvents->where('production_shift_id', $shift->id)->sum('duration_seconds') - $timeToProduceAll;
                
                // Let's try simpler Global Aggregation to avoid per-shift downtime complexity loop
                $fullyProductiveTime += $timeToProduceGood;
                $qualityLossTime += ($reject * $ict);
            }
        }
        
        // Performance Loss is whatever remains of Run Time after accounting for "Net Operating Time" (Time to produce All)
        // Net Operating Time = Fully Productive + Quality Loss
        $netOperatingTime = $fullyProductiveTime + $qualityLossTime;
        $performanceLossTime = max(0, $runTime - $netOperatingTime);

        return response()->json([
            'waterfall' => [
                [
                    'name' => 'Total Scheduled Time',
                    'value' => round($totalShiftTime / 3600, 2), // Hours
                    'type' => 'total'
                ],
                [
                    'name' => 'Planned Downtime',
                    'value' => round($plannedDowntime / 3600, 2),
                    'type' => 'loss',
                    'category' => 'availability'
                ],
                [
                    'name' => 'Unplanned Downtime',
                    'value' => round($unplannedDowntime / 3600, 2),
                    'type' => 'loss',
                    'category' => 'availability'
                ],
                [
                    'name' => 'Net Run Time',
                    'value' => round($runTime / 3600, 2),
                    'type' => 'subtotal'
                ],
                [
                    'name' => 'Performance Loss',
                    'value' => round($performanceLossTime / 3600, 2),
                    'type' => 'loss',
                    'category' => 'performance'
                ],
                [
                    'name' => 'Quality Loss',
                    'value' => round($qualityLossTime / 3600, 2),
                    'type' => 'loss',
                    'category' => 'quality'
                ],
                [
                    'name' => 'Fully Productive Time',
                    'value' => round($fullyProductiveTime / 3600, 2),
                    'type' => 'final'
                ]
            ],
            'meta' => [
                'total_shifts' => $shifts->count(),
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ]);
    }

    /**
     * Get Cycle Time Analysis (Scatter Plot)
     * Plot: Log Date vs (Actual Run Rate vs Ideal Run Rate)
     */
    public function cycleTime(Request $request)
    {
        $machineId = $request->input('machine_id');
        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $shifts = ProductionShift::query()
            ->where('status', 'completed')
            ->whereBetween('started_at', [$dateFrom, $dateTo . ' 23:59:59']);

        if ($machineId) {
            $shifts->where('machine_id', $machineId);
        }
        
        $data = $shifts->get()->map(function($shift) {
            $good = (int)($shift->good_count ?? $shift->metadata['good_count'] ?? 0);
            $reject = (int)($shift->reject_count ?? $shift->metadata['reject_count'] ?? 0);
            $total = $good + $reject;
            
            // Get Downtime for this shift to calculate Net Run Time
            // Get Downtime for this shift to calculate Net Run Time (Correctly done below)
            
            $start = $shift->started_at;
            $end = $shift->ended_at ?? now();
            $elapsed = $start->diffInSeconds($end);
            
            // Downtime Lookup
            $dtSeconds = DB::table('downtime_events')->where('production_shift_id', $shift->id)->sum('duration_seconds');
            
            $runTime = max(1, $elapsed - $dtSeconds);
            
            // Actual Rate (Units / Hour)
            $actualRate = ($total / $runTime) * 3600;
            
            // Ideal Rate
            $idealRate = $shift->metadata['ideal_rate'] ?? 0;
            
            return [
                'date' => $start->toDateTimeString(),
                'actual_rate' => round($actualRate, 1),
                'ideal_rate' => round($idealRate, 1),
                'shift_name' => $shift->shift->name ?? 'Unknown',
                'product' => $shift->product->name ?? 'Unknown',
                'run_time_mins' => round($runTime / 60, 1),
                'total_units' => $total
            ];
        })->filter(function($item) {
            return $item['total_units'] > 0 && $item['ideal_rate'] > 0; // Filter out zero-production data
        })->values();

        return response()->json([
            'points' => $data
        ]);
    }
}
