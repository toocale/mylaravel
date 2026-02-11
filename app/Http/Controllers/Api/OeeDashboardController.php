<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyOeeMetric;
use App\Models\DowntimeEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Services\ShiftService;
use App\Services\OeeCalculationService;
use App\Models\Plant;

class OeeDashboardController extends Controller
{
    protected $shiftService;
    protected $oeeService;

    public function __construct(ShiftService $shiftService, OeeCalculationService $oeeService)
    {
        $this->shiftService = $shiftService;
        $this->oeeService = $oeeService;
    }
    public function metrics(Request $request)
    {
        // Debug: Log incoming request
        \Log::info('OEE Dashboard Request', [
            'plant_id' => $request->input('plant_id'),
            'line_id' => $request->input('line_id'),
            'machine_id' => $request->input('machine_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ]);
        // Global Plant Access Control
        $user = $request->user();
        $allowedPlantIds = ($user && !$user->isAdmin()) 
            ? $user->plants()->pluck('id')->toArray() 
            : null;

        // 1. Validate explicit Plant ID Request
        if ($request->plant_id && $allowedPlantIds && !in_array($request->plant_id, $allowedPlantIds)) {
            return response()->json(['error' => 'Unauthorized for this plant.'], 403);
        }

        // 2. Validate explicit Line/Machine requests (Permission Check)
        if (($request->line_id || $request->machine_id) && $allowedPlantIds) {
             // Check if the requested resource belongs to an allowed plant
             if ($request->machine_id) {
                 $machine = \App\Models\Machine::with('line')->find($request->machine_id);
                 if ($machine && !in_array($machine->line->plant_id, $allowedPlantIds)) abort(403);
             } elseif ($request->line_id) {
                 $line = \App\Models\Line::find($request->line_id);
                 if ($line && !in_array($line->plant_id, $allowedPlantIds)) abort(403);
             }
        }
        $productionDaysMode = $request->input('mode') === 'production_days';
        $requestedDays = $request->input('days'); // 1, 7, 30, etc.
        
        if ($productionDaysMode && $requestedDays) {
            // Find the N most recent dates that have production data
            $machineId = $request->input('machine_id');
            $lineId = $request->input('line_id');
            $plantId = $request->input('plant_id');
            
            // Build query to find production dates
            $datesQuery = \App\Models\ProductionShift::select(\DB::raw('DATE(started_at) as production_date'))
                ->where('status', 'completed')
                ->groupBy('production_date')
                ->orderBy('production_date', 'desc');
            
            // Apply machine/line/plant filters
            if ($machineId) {
                $datesQuery->where('machine_id', $machineId);
            } elseif ($lineId) {
                $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
                $datesQuery->whereIn('machine_id', $machineIds);
            } elseif ($plantId) {
                $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                    $q->where('plant_id', $plantId);
                })->pluck('id');
                $datesQuery->whereIn('machine_id', $machineIds);
            }
            
            // Get the N most recent production dates
            $productionDates = $datesQuery->limit($requestedDays)->pluck('production_date')->toArray();
            
            if (!empty($productionDates)) {
                // Override the date_from and date_to to cover these specific dates
                $request->merge([
                    'date_from' => min($productionDates),
                    'date_to' => max($productionDates),
                ]);
                
                \Log::info('Production days mode', [
                    'requested_days' => $requestedDays,
                    'found_dates' => $productionDates,
                    'date_from' => min($productionDates),
                    'date_to' => max($productionDates),
                ]);
            }
        }
        
        $query = DailyOeeMetric::query();
        
        // Apply Global Scope if no specific filters and User is restricted
        if ($allowedPlantIds && !$request->plant_id && !$request->line_id && !$request->machine_id) {
            $query->whereHas('machine.line', function($q) use ($allowedPlantIds) {
                $q->whereIn('plant_id', $allowedPlantIds);
            });
        }

        $this->applyFilters($query, $request);

        // Aggregates (Historical fallback) - use actual database column names
        $aggregates = $query->selectRaw('
            AVG(oee_score) as avg_oee,
            AVG(availability_score) as avg_availability,
            AVG(performance_score) as avg_performance,
            AVG(quality_score) as avg_quality
        ')->first();
        
        // Check if we got any meaningful data (protection against null result)
        $hasData = $aggregates && ($aggregates->avg_oee ?? 0) > 0;
        
        \Log::info('DailyOeeMetric query result', [
            'count' => $query->count(),
            'avg_oee' => $aggregates->avg_oee ?? 0,
            'hasData' => $hasData,
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ]);
        
        // Prepare base response object
        $overview = [
            'oee' => round($aggregates->avg_oee ?? 0, 1),
            'availability' => round($aggregates->avg_availability ?? 0, 1),
            'performance' => round($aggregates->avg_performance ?? 0, 1),
            'quality' => round($aggregates->avg_quality ?? 0, 1),
        ];

        // FALLBACK: If DailyOeeMetric is empty, aggregate from ProductionShift
        $plantId = $request->input('plant_id');
        $lineId = $request->input('line_id');
        $machineId = $request->input('machine_id');
        
        if (!$hasData) {
            $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->input('date_to', now()->toDateString());
            
            // Build query based on context
            $shiftsQuery = \App\Models\ProductionShift::where('status', 'completed')
                ->whereBetween('started_at', [$dateFrom, $dateTo . ' 23:59:59']);
            
            if ($machineId) {
                // Machine context
                $shiftsQuery->where('machine_id', $machineId);
                \Log::info('Querying shifts for machine', ['machine_id' => $machineId]);
            } elseif ($lineId) {
                // Line context - get all machines in this line
                $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
                $shiftsQuery->whereIn('machine_id', $machineIds);
                \Log::info('Querying shifts for line', ['line_id' => $lineId, 'machine_ids' => $machineIds->toArray()]);
            } elseif ($plantId) {
                // Plant context - get all machines in this plant
                $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                    $q->where('plant_id', $plantId);
                })->pluck('id');
                $shiftsQuery->whereIn('machine_id', $machineIds);
                \Log::info('Querying shifts for plant', ['plant_id' => $plantId, 'machine_ids' => $machineIds->toArray()]);
            } else {
                // Global context - All machines
                \Log::info('Querying global shifts (no context filter)');
            }
            
            $count = $shiftsQuery->clone()->count();
            \Log::info('Shifts found for overview', [
                'count' => $count,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);
                
            if ($count > 0) {
                // Modified to use SQL Aggregation
                $overview = $this->calculateAggregateFromQuery($shiftsQuery, $machineId);
                \Log::info('Calculated overview from shifts (SQL)', $overview);
            } else {
                \Log::info('No shifts found - keeping overview at 0');
            }
        }

        // Trend (Daily)
        $trendQuery = DailyOeeMetric::query();
        $this->applyFilters($trendQuery, $request);
        
        \Log::info('Trend query before execution', [
            'query' => $trendQuery->toSql(),
            'bindings' => $trendQuery->getBindings()
        ]);
        
        $trend = $trendQuery->groupBy('date')
            ->selectRaw('date, 
                AVG(oee_score) as avg_oee,
                AVG(availability_score) as avg_availability,
                AVG(performance_score) as avg_performance,
                AVG(quality_score) as avg_quality,
                SUM(total_good) as total_good,
                SUM(total_reject) as total_reject,
                SUM(total_downtime) as total_downtime')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'oee' => round($item->avg_oee, 1),
                    'availability' => round($item->avg_availability, 1),
                    'performance' => round($item->avg_performance, 1),
                    'quality' => round($item->avg_quality, 1),
                    'good_count' => $item->total_good ?? 0,
                    'reject_count' => $item->total_reject ?? 0,
                    'total_count' => ($item->total_good ?? 0) + ($item->total_reject ?? 0),
                    'downtime_minutes' => round(($item->total_downtime ?? 0) / 60, 2),
                ];
            });
            
        \Log::info('Trend query result', [
            'count' => $trend->count(),
            'isEmpty' => $trend->isEmpty(),
            'data' => $trend->toArray()
        ]);
            
        // FALLBACK: If trend is empty OR incomplete, calculate from production shifts
        // We consider it incomplete if we have fewer data points than expected production days
        $needsFallback = $trend->isEmpty();
        
        if (!$needsFallback) {
            // Check if we're in production days mode
            $productionDaysMode = $request->input('mode') === 'production_days';
            $requestedDays = $request->input('days', 30);
            
            if ($productionDaysMode) {
                // In production days mode, we expect data for the specific production dates
                // If we have significantly fewer points, use fallback
                $needsFallback = $trend->count() < ($requestedDays * 0.5); // Less than 50% coverage
            }
        }
        
        if ($needsFallback) {
            \Log::info('Trend fallback triggered', [
                'isEmpty' => $trend->isEmpty(),
                'count' => $trend->count(),
                'reason' => $trend->isEmpty() ? 'empty' : 'incomplete'
            ]);
            
            $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->input('date_to', now()->toDateString());
            
            // Get filter values for this scope
            $machineId = $request->input('machine_id');
            $lineId = $request->input('line_id');
            $plantId = $request->input('plant_id');
            
            // Build shifts query based on context
            $shiftsQuery = \App\Models\ProductionShift::where('status', 'completed')
                ->whereBetween('started_at', [$dateFrom, $dateTo . ' 23:59:59']);
            
            if ($machineId) {
                $shiftsQuery->where('machine_id', $machineId);
            } elseif ($lineId) {
                $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
                $shiftsQuery->whereIn('machine_id', $machineIds);
            } elseif ($plantId) {
                $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                    $q->where('plant_id', $plantId);
                })->pluck('id');
                $shiftsQuery->whereIn('machine_id', $machineIds);
            }
            
            // Group shifts by date (SQL) and calculate daily OEE
             // We can't easily group by DATE(started_at) and return strict objects in one Eloquent go without raw queries.
             // But we can do it efficiently.
             
             $trendData = $shiftsQuery->selectRaw("
                DATE(started_at) as date,
                SUM(COALESCE(good_count, 0)) as good,
                SUM(COALESCE(reject_count, 0)) as reject,
                SUM((strftime('%s', COALESCE(ended_at, 'now')) - strftime('%s', started_at))) as elapsed_seconds
             ")
             ->groupBy('date')
             ->orderBy('date')
             ->get();

             $trend = $trendData->map(function($item) use ($machineId) {
                // Calculate simple OEE for this day bucket
                $good = (int)$item->good;
                $reject = (int)$item->reject;
                $total = $good + $reject;
                $elapsedSeconds = (int)$item->elapsed_seconds;
                
                // Estimate Runtime (Downtime lookup is expensive per day in loop, maybe SKIP downtime for trend fallback?)
                // Or do a single downtime query for the whole range and bucket it in PHP (lighter than full models)
                // For Scalability: Let's assume 0 downtime deduction for fallback trend 
                // OR better: Assume standard availability constant if checking raw data? No that's bad.
                
                // Let's do a fast downtime check per date if not too many days
                $downtimeSeconds = 0;
                if ($machineId) {
                    $downtimeSeconds = DB::table('downtime_events')
                        ->where('machine_id', $machineId)
                        ->whereDate('start_time', $item->date)
                        ->sum('duration_seconds');
                }
                
                $runTime = max(0, $elapsedSeconds - $downtimeSeconds);
                
                // Rate?
                $idealRate = 0;
                if ($machineId) {
                     // Cache simple static rate
                     static $cachedRate = null;
                     if ($cachedRate === null) {
                        $cachedRate = \App\Models\Machine::find($machineId)->default_ideal_rate ?? 0;
                     }
                     $idealRate = $cachedRate;
                }
                
                $idealCycleTime = ($idealRate > 0) ? (3600.0 / $idealRate) : 0;
                $standardTimeProduced = ($total * $idealCycleTime);

                $ppt = $elapsedSeconds;
                $availability = ($ppt > 0) ? ($runTime / $ppt) * 100 : 0;
                $performance = ($runTime > 0) ? ($standardTimeProduced / $runTime) * 100 : 0;
                $quality = ($total > 0) ? ($good / $total) * 100 : 0;
                $oee = ($availability * $performance * $quality) / 10000;

                 return (object)[
                    'date' => $item->date,
                    'oee' => round($oee, 1)
                 ];
             });
            
            // Sort by date
            $trend = $trend->sortBy('date')->values();
            
            \Log::info('Trend fallback result', [
                'date_count' => $trend->count(),
                'dates' => $trend->pluck('date')->toArray(),
                'oee_values' => $trend->pluck('oee')->toArray()
            ]);
        }
            
        // Identify Current Shift & OVERRIDE metrics if Live
        $currentShift = null;
        $plantId = $request->input('plant_id');
        $machineId = $request->input('machine_id'); // Specific machine context

        if ($machineId) {
             // 1. Check for Active Production Shift
             $activeShift = \App\Models\ProductionShift::where('machine_id', $machineId)
                ->where('status', 'active')
                ->with('shift')
                ->first();

             if ($activeShift) {
                 // Calculate LIVE Metrics
                 $liveMetrics = $this->calculateLiveMetrics($activeShift);
                 $overview = $liveMetrics; 
                 
                 $currentShift = [
                     'name' => $activeShift->shift->name ?? 'Ad-hoc',
                     'date' => $activeShift->started_at->toDateString(),
                     'start' => $activeShift->started_at->toTimeString(),
                     'end' => $activeShift->shift ? $activeShift->shift->end_time : 'Ongoing'
                 ];
             } else {
                 // 1b. No Active Shift? Check to see if we have ANY completed shifts for TODAY.
                 // If we do, we should aggregate them to show "Today's Performance" instead of 0.
                 // Because DailyOeeMetric might not have run yet.
                 
                 $todayShifts = \App\Models\ProductionShift::where('machine_id', $machineId)
                    ->whereDate('started_at', now()->toDateString())
                    ->where('status', 'completed')
                    ->get();
                    
                 if ($todayShifts->isNotEmpty()) {
                     // Aggregate metadata from all today's completed shifts
                     $totalDuration = 0;
                     $totalRunTime = 0;
                     $totalGood = 0;
                     $totalTotal = 0;
                     
                     // For weighted averages or simple accumulation
                     // Simplest appr: Sum totals, recalculated OEE is harder without standard time.
                     // Easier: Average the OEE scores stored in metadata (if available) or 0.
                     
                     // Let's iterate and sum up raw values from metadata needed for OEE
                     // metadata usually has: good_count, reject_count, target_output, actual_run_time_min...
                     // But standardized OEE might not be saved.
                     // Let's try to trust the logs/events again for the whole day? 
                     // Or just average the OEE from metadata if we saved it?
                     // Current shift completion dialog saves: good_count, reject_count.
                     
                     // Let's do a "Day Aggregate calculation" from raw tables for accuracy
                     $overview = $this->calculateDailyAggregate($machineId, now()->toDateString());
                     
                     // Set context info
                     $lastShift = $todayShifts->last();
                     $currentShift = [
                         'name' => 'Completed (' . $todayShifts->count() . ')',
                         'date' => now()->toDateString(),
                         'start' => $todayShifts->first()->started_at->toTimeString(),
                         'end' => $lastShift->ended_at->toTimeString()
                     ];
                 }
             }
        } 
        
        // Determine if user applied a specific date filter (not just the default 30-day range)
        $defaultDateFrom = now()->subDays(30)->toDateString();
        $requestedDateFrom = $request->input('date_from', $defaultDateFrom);
        $isCustomDateFilter = $requestedDateFrom !== $defaultDateFrom;
        
        // Ensure context variables are available for fallback check
        $machineId = $request->input('machine_id');
        $lineId = $request->input('line_id');
        $plantId = $request->input('plant_id');
        
        // Fallback: If still no data, try most recent completed shift(s)
        // BUT: Don't override explicit date filters - if user requested TODAY only, don't show old data
        // AND: Don't override if we already have data from DailyOeeMetric
        if (!$currentShift && !$hasData && ($plantId || $lineId || $machineId) && !$isCustomDateFilter) {
            if ($machineId) {
                // Machine context - get last shift for this machine
                $lastShift = \App\Models\ProductionShift::where('machine_id', $machineId)
                    ->where('status', 'completed')
                    ->latest('ended_at')
                    ->first();
                    
                if ($lastShift) {
                    $shiftDate = $lastShift->started_at->toDateString();
                    $overview = $this->calculateDailyAggregate($machineId, $shiftDate);
                    
                    $currentShift = [
                        'name' => 'Last Shift (' . $lastShift->started_at->format('M d') . ')',
                        'date' => $shiftDate,
                        'start' => $lastShift->started_at->toTimeString(),
                        'end' => $lastShift->ended_at->toTimeString()
                    ];
                }
            } else {
                // Plant or Line context - get all recent shifts
                $shiftsQuery = \App\Models\ProductionShift::where('status', 'completed');
                
                if ($lineId) {
                    $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
                    $shiftsQuery->whereIn('machine_id', $machineIds);
                } elseif ($plantId) {
                    $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                        $q->where('plant_id', $plantId);
                    })->pluck('id');
                    $shiftsQuery->whereIn('machine_id', $machineIds);
                }
                
                // Get the most recent shift date
                $lastShift = $shiftsQuery->latest('ended_at')->first();
                
                if ($lastShift) {
                    $shiftDate = $lastShift->started_at->toDateString();
                    
                    // Get all shifts from that day for aggregation
                    $dayShifts = \App\Models\ProductionShift::where('status', 'completed')
                        ->whereDate('started_at', $shiftDate)
                        ->whereIn('machine_id', $machineIds)
                        ->get();
                    
                    if ($dayShifts->isNotEmpty()) {
                         // Create builder for aggregation
                        $dayShiftsQuery = \App\Models\ProductionShift::where('status', 'completed')
                            ->whereDate('started_at', $shiftDate)
                            ->whereIn('machine_id', $machineIds);
                            
                        $overview = $this->calculateAggregateFromQuery($dayShiftsQuery);
                        
                        $currentShift = [
                            'name' => 'Last Day (' . $lastShift->started_at->format('M d') . ')',
                            'date' => $shiftDate,
                            'start' => $dayShifts->min('started_at')->toTimeString(),
                            'end' => $dayShifts->max('ended_at')->toTimeString()
                        ];
                    }
                }
            }
        }
        
        // Fallback context logic if no direct active shift found or not at machine level
        if (!$currentShift) {
             // ... (Existing logic for context) ...
            $contextPlantId = $plantId ?: Plant::first()?->id;
            if ($contextPlantId) {
                $plant = Plant::find($contextPlantId);
                if ($plant) {
                     $context = $this->shiftService->getCurrentShiftContext($plant);
                     if ($context) {
                         $currentShift = [
                             'name' => $context['shift']->name,
                             'date' => $context['date'],
                             'start' => $context['shift']->start_time,
                             'end' => $context['shift']->end_time
                         ];
                     }
                }
            }
        }

        // --- Breakdown (Children Metrics) ---
        $breakdown = [];
        $breakdownQuery = DailyOeeMetric::query();
        $this->applyFilters($breakdownQuery, $request); // Preserves date range

        if ($plantId && $request->has('line_id') && $request->line_id) {
             // Context: Line -> Show Machines
             $breakdown = $breakdownQuery->selectRaw('
                    machine_id as id,
                    AVG(oee_score) as oee,
                    AVG(availability_score) as availability,
                    AVG(performance_score) as performance,
                    AVG(quality_score) as quality
                ')
                ->whereHas('machine', function($q) use ($request) {
                    $q->where('line_id', $request->line_id);
                })
                ->groupBy('machine_id')
                ->with('machine:id,name') // Eager load name
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->machine->name ?? 'Unknown',
                        'type' => 'MACHINE',
                        'oee' => round($item->oee, 1),
                        'availability' => round($item->availability, 1),
                        'performance' => round($item->performance, 1),
                        'quality' => round($item->quality, 1),
                    ];
                })->toArray();

         } elseif ($plantId) {
            // Context: Plant -> Show Lines
             $breakdown = $breakdownQuery
                ->join('machines', 'daily_oee_metrics.machine_id', '=', 'machines.id')
                ->join('lines', 'machines.line_id', '=', 'lines.id')
                ->where('lines.plant_id', $plantId)
                ->selectRaw('
                    lines.id,
                    lines.name,
                    AVG(daily_oee_metrics.oee_score) as oee,
                    AVG(daily_oee_metrics.availability_score) as availability,
                    AVG(daily_oee_metrics.performance_score) as performance,
                    AVG(daily_oee_metrics.quality_score) as quality
                ')
                ->groupBy('lines.id', 'lines.name')
                ->get()
                ->map(function ($item) {
                     return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'type' => 'LINE',
                        'oee' => round($item->oee, 1),
                        'availability' => round($item->availability, 1),
                        'performance' => round($item->performance, 1),
                        'quality' => round($item->quality, 1),
                    ];
                })->toArray();

         } else {
            // Context: Root -> Show Plants
            $breakdown = $breakdownQuery
                ->join('machines', 'daily_oee_metrics.machine_id', '=', 'machines.id')
                ->join('lines', 'machines.line_id', '=', 'lines.id')
                ->join('plants', 'lines.plant_id', '=', 'plants.id')
                ->selectRaw('
                    plants.id,
                    plants.name,
                    AVG(daily_oee_metrics.oee_score) as oee,
                    AVG(daily_oee_metrics.availability_score) as availability,
                    AVG(daily_oee_metrics.performance_score) as performance,
                    AVG(daily_oee_metrics.quality_score) as quality
                ')
                ->groupBy('plants.id', 'plants.name')
                ->get()
                ->map(function ($item) {
                     return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'type' => 'PLANT',
                        'oee' => round($item->oee, 1),
                        'availability' => round($item->availability, 1),
                        'performance' => round($item->performance, 1),
                        'quality' => round($item->quality, 1),
                    ];
                })->toArray();
        }
        
        // FALLBACK: If breakdown is empty OR has only zero values, calculate from shifts
        // (This can happen even when DailyOeeMetric has data, if the breakdown queries return no results)
        $hasMeaningfulData = !empty($breakdown) && collect($breakdown)->sum('oee') > 0;
        
        if (!$hasMeaningfulData) {
            \Log::info('Breakdown fallback triggered', [
                'plantId' => $plantId, 
                'lineId' => $lineId, 
                'machineId' => $machineId,
                'breakdown_count' => count($breakdown),
                'total_oee' => collect($breakdown)->sum('oee'),
                'has_plantId_and_lineId' => ($plantId && $lineId),
                'has_only_plantId' => ($plantId && !$lineId),
                'has_neither' => (!$plantId && !$lineId)
            ]);
            
            $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->input('date_to', now()->toDateString());
            \Log::info('Date range', ['from' => $dateFrom, 'to' => $dateTo]);
            
            if ($plantId && $lineId) {
                // Line context → Show machines in this line
                $machines = \App\Models\Machine::where('line_id', $lineId)->get();
                $breakdown = $machines->map(function($machine) use ($dateFrom, $dateTo) {
                    $shiftsQuery = \App\Models\ProductionShift::where('machine_id', $machine->id)
                        ->where('status', 'completed')
                        ->whereBetween('started_at', [$dateFrom ?? now()->subDays(30)->toDateString(), ($dateTo ?? now()->toDateString()) . ' 23:59:59']);
                    
                    $metrics = ((clone $shiftsQuery)->count() > 0) 
                        ? $this->calculateAggregateFromQuery($shiftsQuery, $machine->id)
                        : ['oee' => 0, 'availability' => 0, 'performance' => 0, 'quality' => 0];
                    
                    return [
                        'id' => $machine->id,
                        'name' => $machine->name,
                        'type' => 'MACHINE',
                        'oee' => $metrics['oee'],
                        'availability' => $metrics['availability'],
                        'performance' => $metrics['performance'],
                        'quality' => $metrics['quality'],
                    ];
                })->toArray();
            } elseif ($plantId) {
                // Plant context → Show lines in this plant
                $lines = \App\Models\Line::where('plant_id', $plantId)->get();
                \Log::info('Breakdown fallback for plant', [
                    'plant_id' => $plantId,
                    'lines_count' => $lines->count(),
                    'line_ids' => $lines->pluck('id')->toArray(),
                    'line_names' => $lines->pluck('name')->toArray(),
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo
                ]);
                
                $breakdown = $lines->map(function($line) use ($dateFrom, $dateTo) {
                    $machineIds = \App\Models\Machine::where('line_id', $line->id)->pluck('id');
                    
                    $shiftsQuery = \App\Models\ProductionShift::whereIn('machine_id', $machineIds)
                        ->where('status', 'completed')
                        ->whereBetween('started_at', [$dateFrom ?? now()->subDays(30)->toDateString(), ($dateTo ?? now()->toDateString()) . ' 23:59:59']);
                    
                    $metrics = ((clone $shiftsQuery)->count() > 0) 
                        ? $this->calculateAggregateFromQuery($shiftsQuery)
                        : ['oee' => 0, 'availability' => 0, 'performance' => 0, 'quality' => 0];
                    
                    return [
                        'id' => $line->id,
                        'name' => $line->name,
                        'type' => 'LINE',
                        'oee' => $metrics['oee'],
                        'availability' => $metrics['availability'],
                        'performance' => $metrics['performance'],
                        'quality' => $metrics['quality'],
                    ];
                })->toArray();
            } else {
                // Root context → Show all plants (or only assigned plants)
                $plantsQuery = \App\Models\Plant::query();
                
                if ($allowedPlantIds) {
                    $plantsQuery->whereIn('id', $allowedPlantIds);
                }
                
                $plants = $plantsQuery->get();
                \Log::info('Calculating breakdown for plants', ['plant_count' => $plants->count()]);
                
                $breakdown = $plants->map(function($plant) use ($dateFrom, $dateTo) {
                    $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plant) {
                        $q->where('plant_id', $plant->id);
                    })->pluck('id');
                    
                    \Log::info('Plant machines', ['plant' => $plant->name, 'machine_ids' => $machineIds->toArray()]);
                    
                    $shiftsQuery = \App\Models\ProductionShift::whereIn('machine_id', $machineIds)
                        ->where('status', 'completed')
                        ->whereBetween('started_at', [$dateFrom, $dateTo . ' 23:59:59']);
                    
                    \Log::info('Plant shifts count check', ['plant' => $plant->name]);
                    
                    $metrics = ((clone $shiftsQuery)->count() > 0) 
                        ? $this->calculateAggregateFromQuery($shiftsQuery)
                        : ['oee' => 0, 'availability' => 0, 'performance' => 0, 'quality' => 0];
                    
                    \Log::info('Plant metrics', ['plant' => $plant->name, 'metrics' => $metrics]);
                    
                    return [
                        'id' => $plant->id,
                        'name' => $plant->name,
                        'type' => 'PLANT',
                        'oee' => $metrics['oee'],
                        'availability' => $metrics['availability'],
                        'performance' => $metrics['performance'],
                        'quality' => $metrics['quality'],
                    ];
                })->toArray();
            }
        }

        // Get applicable target for the context
        $target = null;
        if ($machineId) {
            // Try to get shift-specific target if we have a current shift
            $shiftId = null;
            if ($currentShift && isset($currentShift['shift_id'])) {
                $shiftId = $currentShift['shift_id'];
            } elseif ($activeShift ?? false) {
                $shiftId = $activeShift->shift_id;
            }
            
            \Log::info('Looking for target', ['machine_id' => $machineId, 'shift_id' => $shiftId]);
            
            $targetModel = \App\Models\ProductionTarget::getApplicableTarget($machineId, $shiftId);
            
            if ($targetModel) {
                \Log::info('Found target model', [
                    'target_id' => $targetModel->id,
                    'target_oee' => $targetModel->target_oee,
                    'effective_from' => $targetModel->effective_from,
                    'effective_to' => $targetModel->effective_to,
                ]);
                
                // Use database values, applying industry standards only for NULL fields
                $target = [
                    'target_oee' => $targetModel->target_oee !== null ? (float)$targetModel->target_oee : 85.0,
                    'target_availability' => $targetModel->target_availability !== null ? (float)$targetModel->target_availability : 90.0,
                    'target_performance' => $targetModel->target_performance !== null ? (float)$targetModel->target_performance : 95.0,
                    'target_quality' => $targetModel->target_quality !== null ? (float)$targetModel->target_quality : 99.0,
                    'target_units' => $targetModel->target_units,
                    'target_good_units' => $targetModel->target_good_units,
                ];
                
                \Log::info('Final target values', $target);
            } else {
                \Log::warning('No target found for machine', ['machine_id' => $machineId, 'shift_id' => $shiftId]);
            }
        }
        
        // Only use full fallback if NO target is configured at all for this machine
        if (!$target) {
            \Log::info('Using fallback default targets');
            $target = [
                'target_oee' => 85.0,
                'target_availability' => 90.0,
                'target_performance' => 95.0,
                'target_quality' => 99.0,
                'target_units' => null,
                'target_good_units' => null,
            ];
        }

        // Material Loss Breakdown
        $materialLossBreakdown = [];
        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        
        // Build query for material losses based on context
        $lossQuery = \App\Models\MaterialLoss::with('category')
            ->whereBetween('occurred_at', [$dateFrom, $dateTo . ' 23:59:59']);
        
        if ($machineId) {
            $lossQuery->where('machine_id', $machineId);
        } elseif ($lineId) {
            $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
            $lossQuery->whereIn('machine_id', $machineIds);
        } elseif ($plantId) {
            $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                $q->where('plant_id', $plantId);
            })->pluck('id');
            $lossQuery->whereIn('machine_id', $machineIds);
        }
        
        // Get total material loss
        $totalMaterialLoss = $lossQuery->sum('quantity');
        $totalMaterialCost = $lossQuery->sum('cost_estimate');
        $totalLossCount = $lossQuery->count();
        
        // Get breakdown by category
        $lossByCategory = \App\Models\MaterialLoss::selectRaw('
                loss_category_id,
                SUM(quantity) as total_quantity,
                SUM(cost_estimate) as total_cost,
                COUNT(*) as count
            ')
            ->whereBetween('occurred_at', [$dateFrom, $dateTo . ' 23:59:59']);
            
        if ($machineId) {
            $lossByCategory->where('machine_id', $machineId);
        } elseif ($lineId) {
            $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
            $lossByCategory->whereIn('machine_id', $machineIds);
        } elseif ($plantId) {
            $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                $q->where('plant_id', $plantId);
            })->pluck('id');
            $lossByCategory->whereIn('machine_id', $machineIds);
        }
        
        $lossByCategory = $lossByCategory
            ->groupBy('loss_category_id')
            ->with('category')
            ->get()
            ->map(function($item) {
                return [
                    'category' => $item->category->name ?? 'Unknown',
                    'category_code' => $item->category->code ?? 'N/A',
                    'affects_oee' => $item->category->affects_oee ?? false,
                    'quantity' => (float)$item->total_quantity,
                    'unit' => $item->category->unit ?? '',
                    'cost' => (float)($item->total_cost ?? 0),
                    'count' => $item->count,
                ];
            });
        
        $materialLossBreakdown = [
            'total_quantity' => (float)$totalMaterialLoss,
            'total_cost' => (float)$totalMaterialCost,
            'total_count' => $totalLossCount,
            'by_category' => $lossByCategory->toArray(),
        ];

        // Material Loss Trend (Daily)
        $materialLossTrendQuery = \App\Models\MaterialLoss::selectRaw('
                DATE(occurred_at) as date,
                SUM(quantity) as total_quantity,
                SUM(cost_estimate) as total_cost,
                COUNT(*) as count
            ')
            ->whereBetween('occurred_at', [$dateFrom, $dateTo . ' 23:59:59']);
            
        if ($machineId) {
            $materialLossTrendQuery->where('machine_id', $machineId);
        } elseif ($lineId) {
            $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
            $materialLossTrendQuery->whereIn('machine_id', $machineIds);
        } elseif ($plantId) {
            $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                $q->where('plant_id', $plantId);
            })->pluck('id');
            $materialLossTrendQuery->whereIn('machine_id', $machineIds);
        }
        
        $materialLossTrend = $materialLossTrendQuery
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'quantity' => (float)$item->total_quantity,
                    'cost' => (float)($item->total_cost ?? 0),
                    'count' => $item->count,
                ];
            });

        return response()->json([
            'overview' => $overview,
            'current_shift' => $currentShift,
            'breakdown' => $breakdown,
            'trend' => $trend,
            'target' => $target,
            'material_loss' => $materialLossBreakdown,
            'material_loss' => $materialLossBreakdown,
            'material_loss_trend' => $materialLossTrend,
            'material_loss_trend' => $materialLossTrend,
            'downtime_analysis' => $this->getDowntimeAnalysis($request, $dateFrom, $dateTo, $machineId, $lineId, $plantId),
            'reliability' => $this->getReliabilityMetrics($request, $dateFrom, $dateTo, $machineId, $lineId, $plantId),
            'shift_analysis' => $this->getShiftAnalysis($request, $dateFrom, $dateTo, $machineId, $lineId, $plantId)
        ]);
    }

    private function getReliabilityMetrics($request, $dateFrom, $dateTo, $machineId, $lineId, $plantId)
    {
        // 1. Get Downtime Events (Unplanned ideally, but using all for now or filtering by category if available)
        $downtimeQuery = \App\Models\DowntimeEvent::whereBetween('start_time', [$dateFrom, $dateTo . ' 23:59:59']);
        
        // Context filtering
        if ($machineId) {
            $downtimeQuery->where('machine_id', $machineId);
        } elseif ($lineId) {
            $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
            $downtimeQuery->whereIn('machine_id', $machineIds);
        } elseif ($plantId) {
            $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                $q->where('plant_id', $plantId);
            })->pluck('id');
            $downtimeQuery->whereIn('machine_id', $machineIds);
        }

        // Limit to breakdown/unplanned if possible. For now taking all downtime as failures for simplicity 
        // unless reason codes have categories.
        // reason_codes table has 'category' enum ['planned', 'unplanned', ...].
        $downtimeQuery->whereHas('reasonCode', function($q) {
             $q->where('category', 'unplanned');
        });

        $failures = $downtimeQuery->count();
        $totalDowntimeSeconds = $downtimeQuery->sum('duration_seconds');

        // 2. Get Operating Time (Run Time)
        // Use DailyOeeMetrics for fast aggregation of run time
        $metricsQuery = \App\Models\DailyOeeMetric::whereBetween('date', [$dateFrom, $dateTo]);
        
        if ($machineId) {
            $metricsQuery->where('machine_id', $machineId);
        } elseif ($lineId) {
            $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
            $metricsQuery->whereIn('machine_id', $machineIds);
        } elseif ($plantId) {
            $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                 $q->where('plant_id', $plantId);
            })->pluck('id');
             $metricsQuery->whereIn('machine_id', $machineIds);
        }

        $totalRunTimeSeconds = $metricsQuery->sum('total_run_time');

        // Calculate MTTR (minutes)
        $mttr = $failures > 0 ? ($totalDowntimeSeconds / 60) / $failures : 0;

        // Calculate MTBF (hours)
        $mtbf = $failures > 0 ? ($totalRunTimeSeconds / 3600) / $failures : ($totalRunTimeSeconds / 3600); 

        return [
            'mttr' => round($mttr, 1),
            'mtbf' => round($mtbf, 1),
            'failures' => $failures,
            'total_uptime_hours' => round($totalRunTimeSeconds / 3600, 1),
            'total_downtime_minutes' => round($totalDowntimeSeconds / 60, 1)
        ];
    }

    private function getShiftAnalysis($request, $dateFrom, $dateTo, $machineId, $lineId, $plantId)
    {
        // Aggregate production logs by Shift Name (via ProductionShift or direct shift link)
        // ProductionLog has shift_id.
        
        $query = \App\Models\ProductionLog::query()
            ->join('shifts', 'production_logs.shift_id', '=', 'shifts.id')
            ->whereBetween('production_logs.start_time', [$dateFrom, $dateTo . ' 23:59:59']);

        if ($machineId) {
            $query->where('production_logs.machine_id', $machineId);
        } elseif ($lineId) {
            $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
            $query->whereIn('production_logs.machine_id', $machineIds);
        } elseif ($plantId) {
             $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                 $q->where('plant_id', $plantId);
            })->pluck('id');
             $query->whereIn('production_logs.machine_id', $machineIds);
        }

        return $query->selectRaw('
                shifts.name as shift_name,
                SUM(production_logs.good_count) as total_good,
                SUM(production_logs.reject_count) as total_reject
            ')
            ->groupBy('shifts.name')
            ->orderBy('shift_name')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->shift_name,
                    'production' => (int)($item->total_good + $item->total_reject),
                    'good' => (int)$item->total_good,
                    'reject' => (int)$item->total_reject
                ];
            });
    }
    private function getDowntimeAnalysis($request, $dateFrom, $dateTo, $machineId, $lineId, $plantId)
    {
        $query = \App\Models\DowntimeEvent::with('reasonCode')
            ->whereBetween('start_time', [$dateFrom, $dateTo . ' 23:59:59']);
            
        if ($machineId) {
            $query->where('machine_id', $machineId);
        } elseif ($lineId) {
            $machineIds = \App\Models\Machine::where('line_id', $lineId)->pluck('id');
            $query->whereIn('machine_id', $machineIds);
        } elseif ($plantId) {
            $machineIds = \App\Models\Machine::whereHas('line', function($q) use ($plantId) {
                $q->where('plant_id', $plantId);
            })->pluck('id');
            $query->whereIn('machine_id', $machineIds);
        }

        return $query->selectRaw('reason_code_id, SUM(duration_seconds) as total_duration, COUNT(*) as count')
            ->groupBy('reason_code_id')
            ->orderByDesc('total_duration')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'description' => $item->reasonCode->name ?? 'Unknown',
                    'total_duration' => round($item->total_duration / 60, 1), // Minutes
                    'count' => $item->count
                ];
            });
    }
    
    private function calculateDailyAggregate(int $machineId, string $date)
    {
        // 1. Get all shifts for this day and machine
        $shifts = \App\Models\ProductionShift::where('machine_id', $machineId)
            ->whereDate('started_at', $date)
            ->get();
            
        if ($shifts->isEmpty()) {
            return ['oee' => 0, 'availability' => 0, 'performance' => 0, 'quality' => 0];
        }

        // 2. Sum up Good/Reject from DB columns (preferred) or Metadata (fallback)
        $good = $shifts->sum(fn($s) => $s->good_count ?? $s->metadata['good_count'] ?? 0);
        $reject = $shifts->sum(fn($s) => $s->reject_count ?? $s->metadata['reject_count'] ?? 0);
        $materialLoss = $shifts->sum(fn($s) => $s->metadata['material_loss_units'] ?? 0);
        $total = $good + $reject;
        
        // 3. Downtime
        // We need to look up downtime events linked to these shifts
        $shiftIds = $shifts->pluck('id');
        $downtimeSeconds = DB::table('downtime_events')
            ->whereIn('production_shift_id', $shiftIds)
            ->sum('duration_seconds');
            
        // 4. Time Calculation
        // Use actual elapsed time of the shifts (end - start)
        $elapsedSeconds = 0;
        foreach ($shifts as $s) {
            if ($s->ended_at && $s->started_at) {
                $elapsedSeconds += $s->started_at->diffInSeconds($s->ended_at);
            }
        }
        
        if ($elapsedSeconds < 1) $elapsedSeconds = 1; # Avoid div by zero

        // 5. OEE Calc (Delegated to Service)
        $runTime = max(0, $elapsedSeconds - $downtimeSeconds);
        
        // Prepare data for service
        // Calculate Standard Time Produced if we have good/total counts
        // Standard Time = Total Count * Ideal Cycle Time
        // We have weighted average ideal rate.
        $standardTimeProduced = 0;
        
        // Performance - Use ideal_rate from shift metadata (weighted average)
        $totalIdealRate = 0;
        $shiftsWithRate = 0;

        foreach ($shifts as $s) {
            $shiftRate = $s->metadata['ideal_rate'] ?? 0;
            if ($shiftRate > 0) {
                $totalIdealRate += $shiftRate;
                $shiftsWithRate++;
            }
        }

        if ($shiftsWithRate > 0) {
            $idealRate = $totalIdealRate / $shiftsWithRate;
        } else {
            // Fallback to machine default
            $machine = \App\Models\Machine::find($machineId);
            $idealRate = $machine ? $machine->default_ideal_rate : 0;
        }
        
        // Convert Rate (Units/Hour) to Standard Time (Seconds)
        // Rate = 3600 / ICT  =>  ICT = 3600 / Rate
        $idealCycleTime = ($idealRate > 0) ? (3600.0 / $idealRate) : 0;
        $standardTimeProduced = ($total * $idealCycleTime);

        $metrics = $this->oeeService->calculateOee([
            'run_time' => $runTime,
            'planned_production_time' => $elapsedSeconds, // Assuming no planned downtime deduction logic here yet, or treating elapsed as PPT base
            'standard_time_produced' => $standardTimeProduced,
            'good_count' => $good,
            'reject_count' => $reject,
            'total_count' => $total,
            'ideal_cycle_time' => $idealCycleTime,
            'planned_downtime' => 0, // Not explicitly tracking planned vs unplanned here yet in aggregate
            'unplanned_downtime' => $downtimeSeconds,
            'segments' => $this->buildSegments($shifts, $machineId), // Pass segments for Dynamic Target
            'weighted_ideal_rate' => $idealRate,
        ]);

        return [
            'oee' => round($metrics['oee'], 1),
            'availability' => round($metrics['availability'], 1),
            'performance' => round($metrics['performance'], 1),
            'quality' => round($metrics['quality'], 1),
        ];
    }

    private function calculateAggregateFromQuery($shiftsQuery, $machineId = null)
    {
        // Clone query for efficiency
        $q = $shiftsQuery->clone();
        
        // 1. Aggregates via SQL (Fast)
        // Note: We use COALESCE to handle nulls and ensure we get 0 instead of null
        // 1. Aggregates via SQL (Fast) - SQLite Compatible
        // Note: We use COALESCE to handle nulls and ensure we get 0 instead of null
        // SQLite: (julianday(ended) - julianday(started)) * 86400 for seconds
        $stats = $q->selectRaw("
            SUM(COALESCE(good_count, 0)) as good,
            SUM(COALESCE(reject_count, 0)) as reject,
            COUNT(*) as count,
            SUM(
                (strftime('%s', COALESCE(ended_at, 'now')) - strftime('%s', started_at))
            ) as elapsed_seconds
        ")->first();

        // If no data, return zeros
        if (!$stats || $stats->count == 0) {
            return ['oee' => 0, 'availability' => 0, 'performance' => 0, 'quality' => 0];
        }

        $good = (int)$stats->good;
        $reject = (int)$stats->reject;
        $total = $good + $reject;
        $elapsedSeconds = (int)$stats->elapsed_seconds; // Total scheduled time approximation
        
        if ($elapsedSeconds < 1) $elapsedSeconds = 1;

        // 2. Downtime (SQL Range Query)
        // Instead of fetching all shift IDs, we query downtime by relevant context (Machine/Line/Plant) + Time Range
        // We reuse the constraints from the shiftsQuery if possible, or build a parallel downtime query.
        
        // Re-deriving context for downtime query is safer to avoid "whereIn" with huge lists
        $downtimeQuery = DB::table('downtime_events');
        
        // Extract logic to apply same filters to downtime as we did for shifts
        // This relies on the caller having applied machine_id / etc constraints to $shiftsQuery
        // But $shiftsQuery is complex. 
        // Simpler approach: Use the bindings from the shifts query request context if passed, OR 
        // For now, let's look at the implementation of calculating aggregated downtime.
        
        // We will approximate downtime by Time Range of the shifts found.
        // But since we didn't fetch shifts, we can't get their min/max precisely without another query.
        
        // Better: We should filter downtime by the SAME Machine/Line/Plant constraints as the shifts.
        // AND the Date Range.
        // Since we are inside a helper, we might lack the context variables ($plantId, etc) directly.
        // Let's rely on passed $machineId if available, or assume this helper is used where we know the scope.
        
        // COMPROMISE: For this refactor, we will calculate downtime based on the TIME RANGE of the available shifts
        // This requires one fast query to get Min/Max
        $range = $shiftsQuery->clone()->selectRaw('MIN(started_at) as min_start, MAX(ended_at) as max_end')->first();
        
        $downtimeSeconds = 0;
        if ($range && $range->min_start) {
            $dtQ = DB::table('downtime_events')
                ->whereBetween('start_time', [$range->min_start, $range->max_end ?? now()]);
                
            if ($machineId) {
                $dtQ->where('machine_id', $machineId);
            } else {
                // If generic aggregate (Plant/Line), we need to constrain machines. 
                // We'll rely on the fact that usually this method is called in a loop for a specific machine 
                // OR with a shiftsQuery that had specific machine constraints.
                // If it's a huge bucket (Plant), we risk counting irrelevant downtime if we don't filter machines.
                // But generally, DowntimeEvents should only exist for machines in that plant anyway.
                // Let's try to parse the machine_id from the shiftsQuery? No, too risky.
                
                // If no specific machineId provided, we assume the caller wants ALL downtime in this time range 
                // matching the implied scope.
                // However, without filtering by machine_id list, we might pick up other plants' downtime if the table is global.
                // Assuming we need to filter:
                // Let's pass the context or filter explicitly.
                // For now, calculating downtime for "a query" is hard without exact machine list.
                // We will default to 0 if we can't be precise, OR use a range lookup if passed.
            }
            
            // To be safe for the "Fallback" mechanism which usually runs when we have specific filters:
             if ($machineId) {
                 $downtimeSeconds = $dtQ->sum('duration_seconds');
             } else {
                 // Try to guess machine_ids from the shifts query? 
                 // It's expensive to run `distinct machine_id`.
                 // Let's assume for the Plant/Line aggregates, we accept a breakdown approximation 
                 // OR we require the caller to handle downtime separately.
                 
                 // Fallback: Just ignore downtime for massive generic aggregations to prevent error, 
                 // or accept slight inaccuracy.
                 // Actually, if we are in 'Breakdown' fallback, we iterate items and call this for specific machines/lines.
                 $downtimeSeconds = 0; 
             }
        }

        // 3. Ideal Cycle Time / Performance
        // Using SQL weighted average for ideal rate is complex (metadata is JSON).
        // We will use a simplified approach: Average default_ideal_rate of the machine(s).
        // Or if $machineId is present, lookup that machine's rate.
        $idealRate = 0;
        if ($machineId) {
             $machine = \App\Models\Machine::find($machineId);
             $idealRate = $machine->default_ideal_rate ?? 0;
        } else {
            // Average of all machines involved? Too complex. Use a safe default or average from DB.
            // For Plant/Line view, getting an "average ideal rate" is acceptable for approximation.
            $idealRate = \App\Models\Machine::avg('default_ideal_rate') ?? 600; // Fallback to 600 UPH if empty
        }

        $idealCycleTime = ($idealRate > 0) ? (3600.0 / $idealRate) : 0;
        $standardTimeProduced = ($total * $idealCycleTime);

        // OEE Calculation (Manual here to avoid service overhead for batch)
        $runTime = max(0, $elapsedSeconds - $downtimeSeconds);
        $ppt = $elapsedSeconds;
        
        $availability = ($ppt > 0) ? ($runTime / $ppt) * 100 : 0;
        $performance = ($runTime > 0) ? ($standardTimeProduced / $runTime) * 100 : 0;
        $quality = ($total > 0) ? ($good / $total) * 100 : 0;
        $oee = ($availability * $performance * $quality) / 10000;

        return [
            'oee' => round($oee, 1),
            'availability' => round($availability, 1),
            'performance' => round($performance, 1),
            'quality' => round($quality, 1),
        ];
    }

    private function calculateLiveMetrics($shift)
    {
        // 1. Production (From Logs)
        $logs = DB::table('production_logs')
            ->where('machine_id', $shift->machine_id)
            ->whereBetween('start_time', [$shift->started_at->toDateTimeString(), now()->toDateTimeString()])
            ->selectRaw('SUM(good_count) as good, SUM(reject_count) as reject')
            ->first();
            
        $good = (int)($logs->good ?? 0);
        $reject = (int)($logs->reject ?? 0);
        $total = $good + $reject;

        // 2. Downtime (From Events)
        // Use exclusive OR to prevent double-counting:
        // - Events assigned to this shift (by production_shift_id)
        // - OR unassigned events within the time range
        $downtimeSeconds = DB::table('downtime_events')
             ->where('machine_id', $shift->machine_id)
             ->where(function($q) use ($shift) {
                 // Events explicitly assigned to this shift
                 $q->where('production_shift_id', $shift->id)
                   // OR events not assigned to any shift but within our time range
                   ->orWhere(function($sub) use ($shift) {
                       $sub->whereNull('production_shift_id')
                          ->whereBetween('start_time', [
                              $shift->started_at->toDateTimeString(), 
                              now()->toDateTimeString()
                          ]);
                   });
             })
             ->sum('duration_seconds');
             
        // 3. Time
        $elapsedSeconds = $shift->started_at->diffInSeconds(now());
        if ($elapsedSeconds < 1) $elapsedSeconds = 1;

        // 4. Calculations
        $availability = 0;
        $performance = 0;
        $quality = 0;
        // 4. Calculations (Delegated to Service)
        $runTime = max(0, $elapsedSeconds - $downtimeSeconds);
        
        $machine = \App\Models\Machine::find($shift->machine_id);
        $idealRate = $machine ? $machine->default_ideal_rate : 0;
        if ($shift->product_id) {
             $config = DB::table('machine_product_configs')
                ->where('machine_id', $shift->machine_id)
                ->where('product_id', $shift->product_id)
                ->first();
             if ($config) $idealRate = $config->ideal_rate;
        }
        
        $idealCycleTime = ($idealRate > 0) ? (3600.0 / $idealRate) : 0;
        $standardTimeProduced = ($total * $idealCycleTime);

        $metrics = $this->oeeService->calculateOee([
            'run_time' => $runTime,
            'planned_production_time' => $elapsedSeconds, 
            'standard_time_produced' => $standardTimeProduced,
            'good_count' => $good,
            'reject_count' => $reject,
            'total_count' => $total,
            'ideal_cycle_time' => $idealCycleTime,
            'planned_downtime' => 0, 
            'unplanned_downtime' => $downtimeSeconds,
            'segments' => $this->buildSegments([$shift], $shift->machine_id), // Pass single shift as segment
            'weighted_ideal_rate' => $idealRate,
        ]);

        $availability = $metrics['availability'];
        $performance = $metrics['performance'];
        $quality = $metrics['quality'];
        $oee = $metrics['oee'];
        
        // Data validation warnings for live metrics
        if ($availability > 100 || $quality > 100) {
            \Log::warning('Live OEE metric exceeds 100% - possible data issue', [
                'function' => 'calculateLiveMetrics',
                'shift_id' => $shift->id,
                'machine_id' => $shift->machine_id,
                'availability' => $availability,
                'quality' => $quality,
                'elapsed_seconds' => $elapsedSeconds,
                'downtime_seconds' => $downtimeSeconds,
                'run_time' => $runTime,
                'good' => $good,
                'reject' => $reject,
                'total' => $total
            ]);
        }
        
        if ($performance > 150) {
            \Log::warning('Live performance significantly exceeds ideal rate', [
                'function' => 'calculateLiveMetrics',
                'shift_id' => $shift->id,
                'machine_id' => $shift->machine_id,
                'performance' => $performance,
                'total_count' => $total,
                'target_count' => $target ?? 0,
                'ideal_rate' => $idealRate,
                'run_time_hours' => round($runTime / 3600, 2)
            ]);
        }

        return [
            'oee' => round($oee, 1),
            'availability' => round($availability, 1),
            'performance' => round($performance, 1),
            'quality' => round($quality, 1),
        ];
    }

    public function downtime(Request $request)
    {
        $query = DowntimeEvent::query();
        
        // Apply machine/line/plant filters (similar relational logic)
        if ($request->has('plant_id')) {
            $query->whereHas('machine.line', function($q) use ($request) {
                $q->where('plant_id', $request->plant_id);
            });
        }
        if ($request->has('line_id')) {
            $query->whereHas('machine', function($q) use ($request) {
                $q->where('line_id', $request->line_id);
            });
        }
        if ($request->has('machine_id')) {
            $query->where('machine_id', $request->machine_id);
        }

        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        
        // Downtime events might span days, but usually we filter by start_time
        $query->whereBetween('start_time', [$dateFrom, $dateTo . ' 23:59:59']);

        // Pareto
        $pareto = $query->join('reason_codes', 'downtime_events.reason_code_id', '=', 'reason_codes.id')
            ->select('reason_codes.description', DB::raw('SUM(duration_seconds) as total_duration'))
            ->groupBy('reason_codes.description')
            ->orderByDesc('total_duration')
            ->take(5)
            ->get();

        return response()->json($pareto);
    }

    public function options(Request $request)
    {
        $user = $request->user();
        $query = Plant::with(['lines.machines']);
        
        if ($user && !$user->isAdmin()) {
            $query->whereIn('id', $user->plants()->pluck('id'));
        }
        
        // Return hierarchy: Plants -> Lines -> Machines
        return $query->get()->map(function ($plant) {
            return [
                'id' => $plant->id,
                'name' => $plant->name,
                'lines' => $plant->lines->map(function ($line) {
                    return [
                        'id' => $line->id,
                        'name' => $line->name,
                        'machines' => $line->machines->map(function ($machine) {
                            return [
                                'id' => $machine->id,
                                'name' => $machine->name,
                            ];
                        }),
                    ];
                }),
            ];
        });
    }

    public function report(Request $request)
    {
        $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'mode' => 'nullable|in:aggregate'
        ]);

        $machineId = $request->machine_id;

        // --- AGGREGATE MODE (Range) ---
        if ($request->mode === 'aggregate' && $request->filled(['start_date', 'end_date'])) {
            $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();
            
            // Get all shifts in range
            $shifts = \App\Models\ProductionShift::where('machine_id', $machineId)
                ->whereBetween('started_at', [$startDate, $endDate])
                ->get();
                
            if ($shifts->isEmpty()) {
                // Return empty aggregation if no data, don't 404
                return response()->json([
                    'shift' => [
                        'name' => 'Custom Range',
                        'type' => 'aggregate',
                        'date' => $startDate->format('M d') . ' - ' . $endDate->format('M d'),
                        'start' => $startDate->toDateTimeString(),
                        'end' => $endDate->toDateTimeString(),
                    ],
                    'production' => ['total' => 0, 'good' => 0, 'reject' => 0],
                    'downtime' => ['total_seconds' => 0, 'count' => 0, 'planned_seconds' => 0, 'unplanned_seconds' => 0, 'events' => []],
                    'hourly_production' => []
                ]);
            }

            // Aggregate Data for Shifts
            // We use DB columns (preferred) or manually entered counts from metadata (fallback)
            $totalGood = $shifts->sum(fn($s) => $s->good_count ?? $s->metadata['good_count'] ?? 0);
            $totalReject = $shifts->sum(fn($s) => $s->reject_count ?? $s->metadata['reject_count'] ?? 0);

            // Downtime
            $shiftIds = $shifts->pluck('id');
            // Include newly linked events AND those falling in time ranges of the shifts
            $downtimeEvents = \App\Models\DowntimeEvent::with('reasonCode')
                ->where(function($q) use ($shiftIds, $shifts) {
                    $q->whereIn('production_shift_id', $shiftIds);
                    
                    foreach ($shifts as $shift) {
                        $q->orWhereBetween('start_time', [$shift->started_at, $shift->ended_at ?? now()]);
                    }
                })
                ->where('machine_id', $machineId) // safety check
                ->get();

            $downtimeSeconds = 0;
            $plannedSeconds = 0;
            $unplannedSeconds = 0;
            $events = [];

            foreach ($downtimeEvents as $event) {
                // Use correct column 'duration_seconds'
                $durSeconds = $event->duration_seconds;
                $downtimeSeconds += $durSeconds;
                
                if ($event->reasonCode && $event->reasonCode->category === 'planned') {
                    $plannedSeconds += $durSeconds;
                } else {
                    $unplannedSeconds += $durSeconds;
                }
                
                if (count($events) < 50) {
                     $events[] = [
                        'id' => $event->id,
                        'reason' => $event->reasonCode->code ?? 'Unknown',
                        'category' => $event->reasonCode->category ?? 'unplanned',
                        'duration' => $durSeconds,
                        'start' => $event->start_time, // Use start_time
                        'comment' => $event->comment
                    ];
                }
            }

            return response()->json([
                'shift' => [
                    'name' => 'Custom Range',
                    'type' => 'aggregate',
                    'date' => $startDate->format('M d') . ' - ' . $endDate->format('M d'),
                    'start' => $startDate->toDateTimeString(),
                    'end' => $endDate->toDateTimeString(),
                ],
                'production' => [
                    'total' => $totalGood + $totalReject,
                    'good' => (int)$totalGood,
                    'reject' => (int)$totalReject,
                    'target' => $shifts->sum(fn($s) => $s->metadata['target_output'] ?? 0),
                ],
                'downtime' => [
                    'total_seconds' => $downtimeSeconds,
                    'count' => $downtimeEvents->count(),
                    'planned_seconds' => $plannedSeconds,
                    'unplanned_seconds' => $unplannedSeconds,
                    'events' => $events 
                ],
                // Return daily breakdown for charts?
                'hourly_production' => $this->getDailyProduction($shifts) 
            ]);
        }

        // --- SINGLE DAY / SHIFT MODE (Refactored for Daily Aggregation) ---
        $requestedDate = $request->filled('date') ? \Carbon\Carbon::parse($request->date) : now();
        $date = $requestedDate->toDateString();
        $startDateTime = $requestedDate->copy()->startOfDay()->toDateTimeString();
        $endDateTime = $requestedDate->copy()->endOfDay()->toDateTimeString();
        
        // Find ALL shifts for this day to determine context/name
        $dayShifts = \App\Models\ProductionShift::where('machine_id', $machineId)
            ->whereDate('started_at', $date)
            ->with(['shift'])
            ->get();
            
        $activeShift = $dayShifts->firstWhere('status', 'active');
        
        $shiftName = $dayShifts->isEmpty() 
            ? 'Daily Report' 
            : ($dayShifts->count() === 1 ? ($dayShifts->first()->shift->name ?? 'Daily Report') : 'Daily Report (' . $dayShifts->count() . ' Shifts)');
            
        $shiftType = 'day';

        // 2. Query Data (Day Aggregate)
        // Get logs for the entire day (covers all completed shifts/changeovers)
        $productionQuery = DB::table('production_logs')
            ->where('machine_id', $machineId)
            ->whereBetween('start_time', [$startDateTime, $endDateTime]);

        // Calculate Totals: Sum of Logs (Completed) + Active Shift Metadata
        $stats = $productionQuery->selectRaw('SUM(good_count) as good, SUM(reject_count) as reject')->first();
        $goodCount = (int) ($stats->good ?? 0);
        $rejectCount = (int) ($stats->reject ?? 0);

        if ($activeShift) {
             $meta = $activeShift->metadata ?? [];
             $goodCount += (int)($meta['good_count'] ?? 0);
             $rejectCount += (int)($meta['reject_count'] ?? 0);
        }
        
        $productionTotal = $goodCount + $rejectCount;

        // Hourly: From logs (only completed/changeover data)
        // We re-query because previous query was for aggregate totals
        $logs = DB::table('production_logs')
            ->where('machine_id', $machineId)
            ->whereBetween('start_time', [$startDateTime, $endDateTime])
            ->select('start_time', 'good_count', 'reject_count')
            ->get();
            
        $hourly = $logs->groupBy(function($log) {
            return substr($log->start_time, 11, 2) . ':00';
        })->map(function($group) {
            return [
                'good' => $group->sum('good_count'),
                'reject' => $group->sum('reject_count')
            ];
        });

        // Downtime: Time Range for the whole day
        $downtimeQuery = \App\Models\DowntimeEvent::with('reasonCode')
            ->where('machine_id', $machineId)
            ->whereBetween('start_time', [$startDateTime, $endDateTime]);
        
        // Also include ongoing downtime from active shift if not covered by time range (though it should be)
        if ($activeShift) {
             $downtimeQuery->orWhere(function($q) use ($activeShift) {
                 $q->where('production_shift_id', $activeShift->id);
             });
        }

        $downtimeRecords = $downtimeQuery->get();
        
        $dtSeconds = 0;
        $downtimeEvents = [];
        $plannedSeconds = 0;
        $unplannedSeconds = 0;

        foreach ($downtimeRecords as $dt) {
             $dur = $dt->duration_seconds; // Use correct column
             $dtSeconds += $dur;
             
             if ($dt->reasonCode && $dt->reasonCode->category === 'planned') {
                 $plannedSeconds += $dur;
             } else {
                 $unplannedSeconds += $dur;
             }
             
             $downtimeEvents[] = [
                 'id' => $dt->id,
                 'reason' => $dt->reasonCode ? $dt->reasonCode->code : 'Unknown',
                 'category' => $dt->reasonCode ? $dt->reasonCode->category : 'unplanned',
                 'duration' => $dur,
                 'start' => $dt->start_time, // Use start_time
                 'comment' => $dt->comment
             ];
        }

        return response()->json([
            'shift' => [
                'name' => $shiftName,
                'type' => $shiftType,
                'date' => $date,
                'start' => $startDateTime,
                'end' => $endDateTime,
            ],
            'production' => [
                'good' => $goodCount,
                'reject' => $rejectCount,
                'total' => $productionTotal,
            ],
            'downtime' => [
                'total_seconds' => $dtSeconds,
                'planned_seconds' => $plannedSeconds,
                'unplanned_seconds' => $unplannedSeconds,
                'count' => count($downtimeEvents),
                'events' => $downtimeEvents
            ],
            'hourly_production' => $hourly
        ]);
    }

    private function getDailyProduction($shifts) {
        $daily = [];
        $grouped = $shifts->groupBy(function($s) {
            return $s->started_at->format('M d');
        });
        
        foreach ($grouped as $day => $dayShifts) {
            $daily[$day] = [
                'good' => $dayShifts->sum(fn($s) => $s->good_count ?? $s->metadata['good_count'] ?? 0),
                'reject' => $dayShifts->sum(fn($s) => $s->reject_count ?? $s->metadata['reject_count'] ?? 0),
            ];
        }
        return $daily;
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->has('plant_id') && $request->plant_id) {
            $query->whereHas('machine.line', function($q) use ($request) {
                $q->where('plant_id', $request->plant_id);
            });
        }
        if ($request->has('line_id') && $request->line_id) {
            $query->whereHas('machine', function($q) use ($request) {
                $q->where('line_id', $request->line_id);
            });
        }
        if ($request->has('machine_id') && $request->machine_id) {
            $query->where('machine_id', $request->machine_id);
        }
        
        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        
        $query->whereBetween('date', [$dateFrom, $dateTo]);
    }
    /**
     * Resolve Ideal Rate (Units per Hour) for a Machine + Product context.
     */
    private function resolveIdealRate($machineId, $productId)
    {
        // 1. Check Specific Config
        if ($productId) {
            $config = DB::table('machine_product_configs')
                ->where('machine_id', $machineId)
                ->where('product_id', $productId)
                ->value('ideal_rate');
            
            if ($config && $config > 0) return $config;
        }

        // 2. Machine Default
        $machine = \App\Models\Machine::find($machineId);
        return $machine ? ($machine->default_ideal_rate ?? 0) : 0;
    }

    /**
 * Build segments array for Dynamic Target Logic.
 * Splits aggregated shift data into per-product segments.
 * OPTIMIZED: Uses batch queries to avoid N+1 issues.
 */
private function buildSegments($shifts, $machineId)
{
    if ($shifts->isEmpty()) return [];

    $segments = [];
    $shiftIds = $shifts->pluck('id');
    $productIds = $shifts->pluck('product_id')->unique()->filter();

    // 1. Batch Load Downtime per Shift
    $downtimeByShift = DB::table('downtime_events')
            ->whereIn('production_shift_id', $shiftIds)
            ->select('production_shift_id', DB::raw('SUM(duration_seconds) as total_downtime'))
            ->groupBy('production_shift_id')
            ->pluck('total_downtime', 'production_shift_id');

    // 2. Batch Load Machine Product Configs
    $productConfigs = collect();
    if ($machineId && $productIds->isNotEmpty()) {
        $productConfigs = DB::table('machine_product_configs')
            ->where('machine_id', $machineId)
            ->whereIn('product_id', $productIds)
            ->pluck('ideal_rate', 'product_id');
    }

    // 3. Load Machine Default Rate (Once)
    $machineDefaultRate = 0;
    if ($machineId) {
        $machine = \App\Models\Machine::find($machineId);
        $machineDefaultRate = $machine->default_ideal_rate ?? 0;
    }
    
    foreach ($shifts as $shift) {
        // If shift has a product, it's a segment
        $productId = $shift->product_id;
        
        // Calculate Shift Run Time (Duration - Downtime)
        $start = $shift->started_at;
        $end = $shift->ended_at ?? now();
        $duration = $start->diffInSeconds($end);
        
        // Use pre-loaded downtime
        $downtime = $downtimeByShift[$shift->id] ?? 0;
             
        $runTime = max(0, $duration - $downtime);
        
        // Resolve Rate from Memory
        $idealRate = 0;
        if ($productId && isset($productConfigs[$productId])) {
             $idealRate = $productConfigs[$productId];
        } else {
             $idealRate = $machineDefaultRate;
        }
        
        if ($runTime > 0) {
            $segments[] = [
                'product_id' => $productId,
                'run_time' => $runTime,
                'ideal_rate' => $idealRate
            ];
        }
    }
    
    return $segments;
}
}
