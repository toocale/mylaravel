<?php

namespace App\Services;

use App\Models\DailyOeeMetric;
use App\Models\DowntimeEvent;
use App\Models\Machine;
use App\Models\Plant;
use App\Models\ProductionLog;
use App\Models\ProductionShift;
use App\Models\MachineProductConfig;
use App\Models\ReportSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    protected ShiftService $shiftService;

    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    /**
     * Generate report data based on schedule configuration.
     */
    public function generateReport(ReportSchedule $schedule, ?Carbon $date = null): array
    {
        $date = $date ?? now();

        return match($schedule->report_type) {
            'shift' => $this->generateShiftReport($schedule, $date),
            'daily_oee' => $this->generateDailyOeeReport($schedule, $date),
            'downtime' => $this->generateDowntimeReport($schedule, $date),
            'production' => $this->generateProductionReport($schedule, $date),
            default => $this->generateDailyOeeReport($schedule, $date),
        };
    }

    /**
     * Generate shift report data.
     */
    protected function generateShiftReport(ReportSchedule $schedule, Carbon $date): array
    {
        $machine = $schedule->machine;
        
        if (!$machine) {
            return $this->getEmptyShiftReport();
        }

        $machine->load('line.plant');
        $context = $this->shiftService->getCurrentShiftContext($machine->line->plant, $machine);
        
        if (!$context) {
            return $this->getEmptyShiftReport();
        }

        $shift = $context['shift'];
        $shiftDate = $context['date'];
        
        $startDateTime = $shiftDate . ' ' . $shift->start_time;
        $endDateTime = $shiftDate . ' ' . $shift->end_time;
        
        if ($shift->end_time < $shift->start_time) {
            $endDateTime = Carbon::parse($shiftDate)->addDay()->format('Y-m-d') . ' ' . $shift->end_time;
        }

        // Production stats
        $production = DB::table('production_logs')
            ->where('machine_id', $machine->id)
            ->whereBetween('start_time', [$startDateTime, $endDateTime])
            ->selectRaw('SUM(good_count) as good, SUM(reject_count) as reject')
            ->first();

        // Downtime stats
        $downtime = DowntimeEvent::with('reasonCode')
            ->where('machine_id', $machine->id)
            ->whereBetween('start_time', [$startDateTime, $endDateTime])
            ->whereBetween('start_time', [$startDateTime, $endDateTime])
            ->get();

        // Calculate Target & Ideal Rate
        $start = Carbon::parse($startDateTime);
        $end = Carbon::parse($endDateTime);
        $standardDuration = $start->diffInMinutes($end);
        
        $idealRate = 0;
        $target = 0;
        
        // Try to find the actual production shift record for product context
        $prodShift = ProductionShift::where('machine_id', $machine->id)
            ->whereBetween('started_at', [$startDateTime, $endDateTime])
            ->latest()
            ->first();

        if ($prodShift && $prodShift->product_id) {
             $config = MachineProductConfig::where('machine_id', $machine->id)
                ->where('product_id', $prodShift->product_id)
                ->first();
             if ($config) {
                 $idealRate = $config->ideal_rate;
             }
        } 
        
        // Fallback to machine default if no specific product rate found
        if ($idealRate == 0) {
             $idealRate = $machine->default_ideal_rate ?? 0;
        }

        if ($idealRate > 0) {
            $target = floor(($standardDuration / 60) * $idealRate);
        }

        return [
            'shift' => [
                'name' => $shift->name,
                'type' => $shift->type,
                'date' => $shiftDate,
                'start' => $startDateTime,
                'end' => $endDateTime,
            ],
            'machine' => [
                'id' => $machine->id,
                'name' => $machine->name,
                'line' => $machine->line->name,
                'plant' => $machine->line->plant->name,
            ],
            'production' => [
                'good' => (int) ($production->good ?? 0),
                'reject' => (int) ($production->reject ?? 0),
                'total' => (int) (($production->good ?? 0) + ($production->reject ?? 0)),
                'target' => $target,
                'ideal_rate' => $idealRate,
            ],
            'downtime' => [
                'total_seconds' => $downtime->sum('duration_seconds'),
                'planned_seconds' => $downtime->where('reasonCode.category', 'planned')->sum('duration_seconds'),
                'unplanned_seconds' => $downtime->where('reasonCode.category', '!=', 'planned')->sum('duration_seconds'),
                'count' => $downtime->count(),
                'events' => $downtime->map(fn($dt) => [
                    'reason' => $dt->reasonCode->description ?? 'Unknown',
                    'category' => $dt->reasonCode->category ?? 'unplanned',
                    'duration' => $dt->duration_seconds,
                    'start' => $dt->start_time,
                ])->toArray(),
            ],
        ];
    }

    /**
     * Generate daily OEE report data.
     */
    protected function generateDailyOeeReport(ReportSchedule $schedule, Carbon $date): array
    {
        $query = DailyOeeMetric::query()
            ->where('date', $date->toDateString());

        // Apply filters based on schedule scope
        if ($schedule->machine_id) {
            $query->where('machine_id', $schedule->machine_id);
        } elseif ($schedule->line_id) {
            $query->whereHas('machine', fn($q) => $q->where('line_id', $schedule->line_id));
        } elseif ($schedule->plant_id) {
            $query->whereHas('machine.line', fn($q) => $q->where('plant_id', $schedule->plant_id));
        }

        $metrics = $query->selectRaw('
            AVG(oee_score) as oee,
            AVG(availability_score) as availability,
            AVG(performance_score) as performance,
            AVG(quality_score) as quality,
            SUM(total_count) as total_production,
            SUM(good_count) as good_count,
            SUM(reject_count) as reject_count
        ')->first();

        // Get breakdown by machine
        $breakdown = $query->clone()
            ->with('machine:id,name')
            ->get()
            ->map(fn($m) => [
                'machine' => $m->machine->name ?? 'Unknown',
                'oee' => round($m->oee_score, 1),
                'availability' => round($m->availability_score, 1),
                'performance' => round($m->performance_score, 1),
                'quality' => round($m->quality_score, 1),
            ]);

        return [
            'date' => $date->toDateString(),
            'scope' => $this->getScopeLabel($schedule),
            'overview' => [
                'oee' => round($metrics->oee ?? 0, 1),
                'availability' => round($metrics->availability ?? 0, 1),
                'performance' => round($metrics->performance ?? 0, 1),
                'quality' => round($metrics->quality ?? 0, 1),
            ],
            'production' => [
                'total' => (int) ($metrics->total_production ?? 0),
                'good' => (int) ($metrics->good_count ?? 0),
                'reject' => (int) ($metrics->reject_count ?? 0),
            ],
            'breakdown' => $breakdown->toArray(),
            'target' => 85.0,
        ];
    }

    /**
     * Generate downtime report data.
     */
    protected function generateDowntimeReport(ReportSchedule $schedule, Carbon $date): array
    {
        $query = DowntimeEvent::with('reasonCode', 'machine')
            ->whereDate('start_time', $date);

        if ($schedule->machine_id) {
            $query->where('machine_id', $schedule->machine_id);
        } elseif ($schedule->line_id) {
            $query->whereHas('machine', fn($q) => $q->where('line_id', $schedule->line_id));
        } elseif ($schedule->plant_id) {
            $query->whereHas('machine.line', fn($q) => $q->where('plant_id', $schedule->plant_id));
        }

        $events = $query->get();

        // Pareto analysis
        $pareto = $events->groupBy(fn($e) => $e->reasonCode->description ?? 'Unknown')
            ->map(fn($group) => [
                'reason' => $group->first()->reasonCode->description ?? 'Unknown',
                'total_duration' => $group->sum('duration_seconds'),
                'count' => $group->count(),
            ])
            ->sortByDesc('total_duration')
            ->values()
            ->take(10);

        return [
            'date' => $date->toDateString(),
            'scope' => $this->getScopeLabel($schedule),
            'summary' => [
                'total_duration' => $events->sum('duration_seconds'),
                'total_events' => $events->count(),
            ],
            'pareto' => $pareto->toArray(),
            'events' => $events->map(fn($e) => [
                'machine' => $e->machine->name ?? 'Unknown',
                'reason' => $e->reasonCode->description ?? 'Unknown',
                'duration' => $e->duration_seconds,
                'start' => $e->start_time,
                'comment' => $e->comment,
            ])->toArray(),
        ];
    }

    /**
     * Generate production report data.
     */
    protected function generateProductionReport(ReportSchedule $schedule, Carbon $date): array
    {
        $query = ProductionLog::with('machine')
            ->whereDate('start_time', $date);

        if ($schedule->machine_id) {
            $query->where('machine_id', $schedule->machine_id);
        } elseif ($schedule->line_id) {
            $query->whereHas('machine', fn($q) => $q->where('line_id', $schedule->line_id));
        } elseif ($schedule->plant_id) {
            $query->whereHas('machine.line', fn($q) => $q->where('plant_id', $schedule->plant_id));
        }

        $logs = $query->get();

        $byMachine = $logs->groupBy('machine_id')
            ->map(fn($group) => [
                'machine' => $group->first()->machine->name ?? 'Unknown',
                'good' => $group->sum('good_count'),
                'reject' => $group->sum('reject_count'),
                'total' => $group->sum('good_count') + $group->sum('reject_count'),
            ])
            ->values();

        return [
            'date' => $date->toDateString(),
            'scope' => $this->getScopeLabel($schedule),
            'summary' => [
                'total_good' => $logs->sum('good_count'),
                'total_reject' => $logs->sum('reject_count'),
                'total' => $logs->sum('good_count') + $logs->sum('reject_count'),
            ],
            'by_machine' => $byMachine->toArray(),
        ];
    }

    /**
     * Get scope label for the schedule.
     */
    protected function getScopeLabel(ReportSchedule $schedule): string
    {
        if ($schedule->machine_id && $schedule->machine) {
            return "Machine: {$schedule->machine->name}";
        }
        if ($schedule->line_id && $schedule->line) {
            return "Line: {$schedule->line->name}";
        }
        if ($schedule->plant_id && $schedule->plant) {
            return "Plant: {$schedule->plant->name}";
        }
        return "All Plants";
    }

    /**
     * Get empty shift report structure.
     */
    protected function getEmptyShiftReport(): array
    {
        return [
            'shift' => null,
            'machine' => null,
            'production' => ['good' => 0, 'reject' => 0, 'total' => 0],
            'downtime' => ['total_seconds' => 0, 'count' => 0, 'events' => []],
        ];
    }
}
