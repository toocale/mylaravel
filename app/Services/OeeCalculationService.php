<?php

namespace App\Services;

use App\Models\DailyOeeMetric;
use App\Models\Machine;
use App\Models\SiteSetting;
use Carbon\Carbon;

class OeeCalculationService
{
    /**
     * Calculate and update OEE metrics for a specific machine and date.
     *
     * @param Machine $machine
     * @param string|Carbon $date
     * @return DailyOeeMetric
     */
    public function calculateForMachine(Machine $machine, $date)
    {
        $date = Carbon::parse($date)->format('Y-m-d');
        
        // Fetch logs and events
        // Use filtered start_time for logs to capture "Shift Date" not "Entry Date"
        $logs = $machine->productionLogs()->whereDate('start_time', $date)->with('product')->get();
        // Use start_time for downtime to match when it happened
        $downtimeEvents = $machine->downtimeEvents()->whereDate('start_time', $date)->with('reasonCode')->get();

        // 1. Calculate Time Components
        // Default Shift/Day Length: 24 hours for daily aggregation
        // Future improvement: Calculate based on actual assigned Shifts for that day.
        $totalShiftSeconds = 24 * 3600; 

        // Fetch formula settings
        $availabilityMode = SiteSetting::get('formula_availability_mode', 'standard');
        $performanceMode = SiteSetting::get('formula_performance_mode', 'standard');
        $qualityMode = SiteSetting::get('formula_quality_mode', 'standard');

        $availabilityExpr = SiteSetting::get('formula_availability_expression', '(run_time / planned_production_time) * 100');
        $performanceExpr = SiteSetting::get('formula_performance_expression', '(standard_time_produced / run_time) * 100');
        $qualityExpr = SiteSetting::get('formula_quality_expression', '(good_count / total_count) * 100');

        $excludeBreaks = SiteSetting::get('formula_availability_exclude_breaks', '1') == '1';
        $includeRejects = SiteSetting::get('formula_performance_include_rejects', '1') == '1';

        $plannedDowntimeSeconds = 0;
        $unplannedDowntimeSeconds = 0;

        foreach ($downtimeEvents as $event) {
            $seconds = $event->duration_seconds ?? 0;
            if ($event->reasonCode && $event->reasonCode->category === 'planned') {
                $plannedDowntimeSeconds += $seconds;
            } else {
                $unplannedDowntimeSeconds += $seconds;
            }
        }

        // Calculate Standard PPT (Availability Denominator)
        if ($excludeBreaks) {
             $totalPlannedProductionTime = $totalShiftSeconds - $plannedDowntimeSeconds;
        } else {
             $totalPlannedProductionTime = $totalShiftSeconds;
        }

        // Run Time = Actual Running Time (Total - All Downtime)
        $runTime = $totalShiftSeconds - $plannedDowntimeSeconds - $unplannedDowntimeSeconds;

        if ($totalPlannedProductionTime <= 0) $totalPlannedProductionTime = 1; // Prevent div/0

        // 2. Calculate Counts & Standard Time
        $totalGood = 0;
        $totalReject = 0;
        $standardTimeProduced = 0;
        
        // Also fetch from COMPLETED production shifts for the day
        $completedShifts = \App\Models\ProductionShift::where('machine_id', $machine->id)
             ->whereDate('started_at', $date)
             ->get();
        
        // Calculate Total Shift Time dynamically if shifts exist
        if ($completedShifts->count() > 0) {
            $totalShiftSeconds = 0;
            foreach ($completedShifts as $shift) {
                 $start = Carbon::parse($shift->started_at);
                 $end = $shift->ended_at ? Carbon::parse($shift->ended_at) : $start->copy()->addHours(8); // Fallback
                 $totalShiftSeconds += $start->diffInSeconds($end);
            }
            
            // Re-apply logic with new Total Seconds
            if ($excludeBreaks) {
                 $totalPlannedProductionTime = $totalShiftSeconds - $plannedDowntimeSeconds;
            } else {
                 $totalPlannedProductionTime = $totalShiftSeconds;
            }
            $runTime = $totalShiftSeconds - $plannedDowntimeSeconds - $unplannedDowntimeSeconds;
             if ($totalPlannedProductionTime <= 0) $totalPlannedProductionTime = 1;
        }

        // Cache Ideal Rate for products
        $productRates = [];
        $weightedIct = 0; // Track weighted average ICT for context if needed
        
        if ($completedShifts->count() > 0) {
            foreach ($completedShifts as $shift) {
                 $meta = $shift->metadata ?? [];
                 $good = (int)($shift->good_count ?? $meta['good_count'] ?? 0);
                 $reject = (int)($shift->reject_count ?? $meta['reject_count'] ?? 0);
                 
                 $totalGood += $good;
                 $totalReject += $reject;
                 
                 // Calculate Standard Time for this shift
                 $productId = $shift->product_id;
                 if ($productId) {
                    if (!isset($productRates[$productId])) {
                        $rate = $machine->machineProductConfigs()->where('product_id', $productId)->value('ideal_rate');
                        if (is_null($rate)) $rate = $machine->default_ideal_rate ?? 0;
                        $productRates[$productId] = $rate;
                    }
                    $rate = $productRates[$productId];
                    $ict = ($rate > 0) ? (3600.0 / $rate) : 0;
                    
                    // Apply Performance Formula Setting
                    $countForCalc = $includeRejects ? ($good + $reject) : $good;
                    $standardTimeProduced += $countForCalc * $ict;
                 }
            }
        } else {
            // Fallback: Logs (if no shifts recorded but we have logs)
            $totalGood = $logs->sum('good_count');
            $totalReject = $logs->sum('reject_count');
            
            foreach ($logs as $log) {
                $productId = $log->product_id;
                if (!isset($productRates[$productId])) {
                     $rate = $machine->machineProductConfigs()->where('product_id', $productId)->value('ideal_rate');
                     if (is_null($rate)) $rate = $machine->default_ideal_rate ?? 0;
                     $productRates[$productId] = $rate;
                }
                $rate = $productRates[$productId];
                $ict = ($rate > 0) ? (3600.0 / $rate) : 0;
                
                // Apply Performance Formula Setting
                $countForCalc = $includeRejects ? ($log->good_count + $log->reject_count) : $log->good_count;
                $standardTimeProduced += $countForCalc * $ict;
            }
        }

        $totalCount = $totalGood + $totalReject;

        // 3. Calculate Scores (0-100) using Shared Logic
        $scores = $this->calculateOee([
            'run_time' => $runTime,
            'planned_production_time' => $totalPlannedProductionTime,
            'standard_time_produced' => $standardTimeProduced,
            'good_count' => $totalGood,
            'reject_count' => $totalReject,
            'total_count' => $totalCount,
            'total_shift_time' => $totalShiftSeconds,
            'planned_downtime' => $plannedDowntimeSeconds,
            'unplanned_downtime' => $unplannedDowntimeSeconds,
        ]);

        return DailyOeeMetric::updateOrCreate(
            [
                'machine_id' => $machine->id,
                'date' => $date
            ],
            [
                'availability_score' => $scores['availability'],
                'performance_score' => $scores['performance'],
                'quality_score' => $scores['quality'],
                'oee_score' => $scores['oee'],
                'total_good' => $totalGood,
                'total_reject' => $totalReject,
                'total_run_time' => $runTime,
                'total_planned_production_time' => $totalPlannedProductionTime,
                'total_downtime' => $plannedDowntimeSeconds + $unplannedDowntimeSeconds,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Calculate OEE Scores based on system configuration (Standard or Custom Expressions).
     * 
     * @param array $data Required keys: run_time, planned_production_time, standard_time_produced, good_count, total_count
     * @return array [availability, performance, quality, oee]
     */
    public function calculateOee(array $data): array
    {
        // Default values to prevent errors
        $runTime = $data['run_time'] ?? 0;
        $ppt = $data['planned_production_time'] ?? 1;
        $standardTime = $data['standard_time_produced'] ?? 0; // or derived from (total * ideal_cycle_time)
        $good = $data['good_count'] ?? 0;
        $total = $data['total_count'] ?? 0;
        $reject = $data['reject_count'] ?? ($total - $good);
        
        // Prevent Divide by Zero in standard formulas
        if ($ppt <= 0) $ppt = 1;
        
        // Fetch Settings
        $availabilityMode = SiteSetting::get('formula_availability_mode', 'standard');
        $performanceMode = SiteSetting::get('formula_performance_mode', 'standard');
        $qualityMode = SiteSetting::get('formula_quality_mode', 'standard');

        $availabilityExpr = SiteSetting::get('formula_availability_expression', '(run_time / planned_production_time) * 100');
        $performanceExpr = SiteSetting::get('formula_performance_expression', '(standard_time_produced / run_time) * 100');
        $qualityExpr = SiteSetting::get('formula_quality_expression', '(good_count / total_count) * 100');

        // Prepare Variables for Expression Engine
        $variables = array_merge($data, [
            'run_time' => $runTime,
            'planned_production_time' => $ppt,
            'standard_time_produced' => $standardTime,
            'good_count' => $good,
            'reject_count' => $reject,
            'total_count' => $total,
            // Aliases or fallback
            // Aliases or fallback
            'ideal_cycle_time' => $data['ideal_cycle_time'] ?? ($total > 0 ? $standardTime / $total : 0),
            'ideal_run_rate' => ($standardTime > 0) ? ($total / $standardTime) : 0, // Units per Second
            'ideal_run_rate_hourly' => ($standardTime > 0) ? ($total / $standardTime) * 3600 : 0, // Units per Hour
            'planned_downtime' => $data['planned_downtime'] ?? 0,
            'unplanned_downtime' => $data['unplanned_downtime'] ?? 0,
            'total_shift_time' => $data['total_shift_time'] ?? ($ppt + ($data['planned_downtime'] ?? 0)),
        ]);

        $expressionLanguage = new \Symfony\Component\ExpressionLanguage\ExpressionLanguage();

        // 1. Availability
        if ($availabilityMode === 'custom' && !empty($availabilityExpr)) {
            try {
                $availability = $expressionLanguage->evaluate($availabilityExpr, $variables);
            } catch (\Exception $e) {
                \Log::error('Custom Availability Formula Error: ' . $e->getMessage());
                $availability = 0;
            }
        } else {
            $availability = ($runTime / $ppt) * 100;
        }

        // 2. Performance
        if ($performanceMode === 'custom' && !empty($performanceExpr)) {
            try {
                $performance = $expressionLanguage->evaluate($performanceExpr, $variables);
            } catch (\Exception $e) {
                 \Log::error('Custom Performance Formula Error: ' . $e->getMessage());
                $performance = 0;
            }
        } else {
            $performance = ($runTime > 0) ? ($standardTime / $runTime) * 100 : 0;
        }

        // 3. Quality
        if ($qualityMode === 'custom' && !empty($qualityExpr)) {
             try {
                $quality = $expressionLanguage->evaluate($qualityExpr, $variables);
             } catch (\Exception $e) {
                 \Log::error('Custom Quality Formula Error: ' . $e->getMessage());
                 $quality = 0;
             }
        } else {
            $quality = ($total > 0) ? ($good / $total) * 100 : 0;
        }

        // 4. Final OEE
        $oee = ($availability * $performance * $quality) / 10000;

        return [
            'availability' => (float)$availability,
            'performance' => (float)$performance,
            'quality' => (float)$quality,
            'oee' => (float)$oee,
            'target' => (float)($data['target'] ?? 0) // Pass through or calculated
        ];
    }

    /**
     * Calculate Production Target based on configuration.
     * Supports: Static (DB), Dynamic (Segmented logic), Custom (Expression).
     * 
     * @param array $data Context data (run_time, weighted_ideal_rate, segments, etc.)
     * @return float Calculated Target Count
     */
    public function calculateTarget(array $data): float
    {
        $mode = SiteSetting::get('formula_target_mode', 'static');
        $expression = SiteSetting::get('formula_target_expression', 'run_time * weighted_ideal_rate');

        // 1. Static Mode (Default / Fallback)
        // Usually passed in as 'static_target' from the controller (database lookup)
        $target = $data['static_target'] ?? 0;

        // 2. Dynamic Mode (Segmented Logic)
        if ($mode === 'dynamic') {
             // Logic: Sum of (Segment Run Time * Segment Ideal Rate)
             // Requires 'segments' array in data: [{ 'run_time': 3600, 'ideal_rate': 1000 }, ...]
             if (!empty($data['segments']) && is_array($data['segments'])) {
                 $target = 0;
                 foreach ($data['segments'] as $segment) {
                     $segRunTime = $segment['run_time'] ?? 0; // Hours? No, usually seconds in system, need conversion
                     $segRate = $segment['ideal_rate'] ?? 0; // Units per Hour
                     
                     if ($segRunTime > 0 && $segRate > 0) {
                         $target += ($segRunTime / 3600) * $segRate;
                     }
                 }
             } else {
                 // Fallback to Weighted Average if no segments provided
                 $runTime = $data['run_time'] ?? 0;
                 $rate = $data['weighted_ideal_rate'] ?? 0;
                 $target = ($runTime / 3600) * $rate;
             }
        }

        // 3. Custom Expression
        elseif ($mode === 'custom' && !empty($expression)) {
            try {
                $expressionLanguage = new \Symfony\Component\ExpressionLanguage\ExpressionLanguage();
                $variables = array_merge($data, [
                    'run_time' => $data['run_time'] ?? 0, // Seconds
                    'planned_production_time' => $data['planned_production_time'] ?? 0,
                    'weighted_ideal_rate' => $data['weighted_ideal_rate'] ?? 0,
                    'products_count' => count($data['segments'] ?? []),
                ]);
                $target = $expressionLanguage->evaluate($expression, $variables);
            } catch (\Exception $e) {
                \Log::error('Custom Target Formula Error: ' . $e->getMessage());
                // Keep static target as fallback
            }
        }

        return max(0, $target);
    }
}
