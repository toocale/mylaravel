<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\DailyOeeMetric;
use App\Models\Machine;
use App\Models\Plant;
use App\Models\ProductionShift;
use App\Models\DowntimeEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class OeeReportController extends Controller
{
    /**
     * Display the reports index page
     */
    public function index(Request $request)
    {
        // Check if user is admin or has reports.view permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('reports.view')) {
            abort(403, 'Unauthorized access.');
        }
        
        $plantsQuery = Plant::with(['lines.machines']);

        if (!$request->user()->isAdmin()) {
            $plantsQuery->whereIn('id', $request->user()->plants()->pluck('id'));
        }

        $plants = $plantsQuery->get();

        $shiftsQuery = ProductionShift::query();

        if (!$request->user()->isAdmin()) {
            $allowedPlantIds = $request->user()->plants()->pluck('id');
            $shiftsQuery->whereIn('plant_id', $allowedPlantIds);
        }

        $shifts = $shiftsQuery->get();
        
        return Inertia::render('Reports/Index', [
            'plants' => $plants,
            'shifts' => $shifts,
        ]);
    }

    /**
     * Generate OEE report with filters
     */
    public function generate(Request $request)
    {
        // Check if user is admin or has reports.view permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('reports.view')) {
            abort(403, 'Unauthorized access.');
        }
        
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'plant_id' => 'nullable|exists:plants,id',
            'line_id' => 'nullable|exists:lines,id',
            'machine_id' => 'nullable|exists:machines,id',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);
        
        // Permission Verify
        $user = $request->user();
        if (!$user->isAdmin()) {
            $allowedPlantIds = $user->plants()->pluck('id')->toArray();
            
            if ($request->plant_id && !in_array($request->plant_id, $allowedPlantIds)) abort(403, 'Unauthorized Plant');
            
            if ($request->line_id) {
                 $line = \App\Models\Line::find($request->line_id);
                 if ($line && !in_array($line->plant_id, $allowedPlantIds)) abort(403);
            }
            
            if ($request->machine_id) {
                 $machine = \App\Models\Machine::with('line')->find($request->machine_id);
                 if ($machine && !in_array($machine->line->plant_id, $allowedPlantIds)) abort(403);
            }
        }
        

        
        // Permission Check for selected context
        $user = $request->user();
        if (!$user->isAdmin()) {
            $allowedPlantIds = $user->plants()->pluck('id')->toArray();
            
            if ($request->plant_id && !in_array($request->plant_id, $allowedPlantIds)) abort(403);
            
            if ($request->line_id) {
                 $line = \App\Models\Line::find($request->line_id);
                 if ($line && !in_array($line->plant_id, $allowedPlantIds)) abort(403);
            }
            
            if ($request->machine_id) {
                 $machine = \App\Models\Machine::with('line')->find($request->machine_id);
                 if ($machine && !in_array($machine->line->plant_id, $allowedPlantIds)) abort(403);
            }
        }

        $dateFrom = Carbon::parse($request->date_from);
        $dateTo = Carbon::parse($request->date_to);
        
        // Build query based on filters
        $query = DailyOeeMetric::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->with(['machine.line.plant']);

        if ($request->machine_id) {
            $query->where('machine_id', $request->machine_id);
        } elseif ($request->line_id) {
            $query->whereHas('machine', function($q) use ($request) {
                $q->where('line_id', $request->line_id);
            });
        } elseif ($request->plant_id) {
            $query->whereHas('machine.line', function($q) use ($request) {
                $q->where('plant_id', $request->plant_id);
            });
        }

        if ($request->shift_id) {
            $query->where('shift_id', $request->shift_id);
        }

        $metrics = $query->orderBy('date')->get();

        // Calculate summary statistics
        $summary = [
            'total_days' => $metrics->count(),
            'avg_oee' => round($metrics->avg('oee'), 2),
            'avg_availability' => round($metrics->avg('availability'), 2),
            'avg_performance' => round($metrics->avg('performance'), 2),
            'avg_quality' => round($metrics->avg('quality'), 2),
            'best_oee' => $metrics->max('oee'),
            'worst_oee' => $metrics->min('oee'),
            'total_produced' => $metrics->sum('total_count'),
            'total_good' => $metrics->sum('good_count'),
            'total_reject' => $metrics->sum('reject_count'),
        ];

        // Get downtime breakdown for the period
        $downtimeQuery = DowntimeEvent::query()
            ->whereBetween('started_at', [$dateFrom, $dateTo->endOfDay()]);

        if ($request->machine_id) {
            $downtimeQuery->where('machine_id', $request->machine_id);
        }

        // Global Scope for Downtime Query too
        if (!$request->user()->isAdmin()) {
             $allowedPlantIds = $request->user()->plants()->pluck('id');
             $downtimeQuery->whereHas('machine.line', function($q) use ($allowedPlantIds) {
                 $q->whereIn('plant_id', $allowedPlantIds);
             });
        }



        $downtime = $downtimeQuery->with('reasonCode')
            ->get()
            ->groupBy('reason_code_id')
            ->map(function($events) {
                $first = $events->first();
                return [
                    'reason' => $first->reasonCode->description ?? 'Unknown',
                    'category' => $first->reasonCode->category ?? 'other',
                    'total_duration' => $events->sum('duration_minutes'),
                    'count' => $events->count(),
                ];
            })
            ->sortByDesc('total_duration')
            ->values();

        return response()->json([
            'metrics' => $metrics,
            'summary' => $summary,
            'downtime' => $downtime,
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'plant_id' => $request->plant_id,
                'line_id' => $request->line_id,
                'machine_id' => $request->machine_id,
                'shift_id' => $request->shift_id,
            ]
        ]);
    }

    /**
     * Export report as PDF
     */
    public function exportPdf(Request $request)
    {
        // Check if user is admin or has reports.view permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('reports.view')) {
            abort(403, 'Unauthorized access.');
        }
        
        // Generate report data
        $reportData = $this->generateReportData($request);
        
        $pdf = Pdf::loadView('reports.oee-pdf', $reportData);
        
        return $pdf->download('oee-report-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export report as Excel
     */
    public function exportExcel(Request $request)
    {
        // Check if user is admin or has reports.view permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('reports.view')) {
            abort(403, 'Unauthorized access.');
        }
        
        $reportData = $this->generateReportData($request);
        
        return \Excel::download(
            new \App\Exports\OeeReportExport($reportData),
            'oee-report-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Export report as CSV
     */
    public function exportCsv(Request $request)
    {
        // Check if user is admin or has reports.view permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('reports.view')) {
            abort(403, 'Unauthorized access.');
        }
        
        $reportData = $this->generateReportData($request);
        
        return \Excel::download(
            new \App\Exports\OeeReportExport($reportData),
            'oee-report-' . now()->format('Y-m-d') . '.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    /**
     * Send report via email with PDF attachment
     */
    public function sendEmail(Request $request)
    {
        // Check if user is admin or has reports.view permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('reports.view')) {
            abort(403, 'Unauthorized access.');
        }
        
        $request->validate([
            'email' => 'required|email',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'machine_id' => 'nullable|exists:machines,id',
        ]);

        try {
            // Generate report data
            $reportData = $this->generateReportData($request);
            
            // Generate PDF and save temporarily
            $pdf = Pdf::loadView('reports.oee-pdf', $reportData);
            $pdfPath =storage_path('app/temp/oee-report-' . uniqid() . '.pdf');
            
            // Ensure temp directory exists
            if (!file_exists(dirname($pdfPath))) {
                mkdir(dirname($pdfPath), 0755, true);
            }
            
            $pdf->save($pdfPath);
            
            // Send email with PDF attachment
            \Mail::to($request->email)->send(
                new \App\Mail\OeeReportWithPdfMail(
                    $reportData,
                    $pdfPath,
                    $reportData['date_from'],
                    $reportData['date_to']
                )
            );
            
            // Delete temporary PDF after sending
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Report sent successfully to ' . $request->email,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper method to generate report data
     */
    private function generateReportData(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'machine_id' => 'nullable|exists:machines,id',
        ]);

        $dateFrom = Carbon::parse($request->date_from);
        $dateTo = Carbon::parse($request->date_to);
        
        $query = DailyOeeMetric::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->with(['machine.line.plant']);

        if ($request->machine_id) {
            $query->where('machine_id', $request->machine_id);
        }
        
        // Global Scope for Exports
        if (!$request->user()->isAdmin()) {
             $allowedPlantIds = $request->user()->plants()->pluck('id');
             $query->whereHas('machine.line', function($q) use ($allowedPlantIds) {
                 $q->whereIn('plant_id', $allowedPlantIds);
             });
        }

        $metrics = $query->orderBy('date')->get();
        
        // Get site settings for PDF header
        $siteName = \App\Models\SiteSetting::get('site_name', null);
        $siteLogo = \App\Models\SiteSetting::get('site_logo', null);
        
        // Fallback to config if site settings don't exist
        if (!$siteName) {
            $siteName = config('app.name', 'OEE System');
        }
        
        // Convert logo path to absolute path if it exists
        $logoPath = null;
        if ($siteLogo) {
            // Remove leading slash if present
            $siteLogo = ltrim($siteLogo, '/');
            
            // Check if file exists in public storage
            if (Storage::disk('public')->exists($siteLogo)) {
                $logoPath = storage_path('app/public/' . $siteLogo);
            } else {
                // Also check in public folder directly
                $publicPath = public_path($siteLogo);
                if (file_exists($publicPath)) {
                    $logoPath = $publicPath;
                }
            }
        }

        return [
            'metrics' => $metrics,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'site_name' => $siteName,
            'site_logo' => $logoPath,
        ];
    }

    /**
     * Generate yearly OEE report with monthly breakdown
     */
    public function generateYearlyReport(Request $request)
    {
        // Check if user is admin or has reports.view permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('reports.view')) {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'plant_id' => 'nullable|exists:plants,id',
            'line_id' => 'nullable|exists:lines,id',
            'machine_id' => 'nullable|exists:machines,id',
        ]);

        $year = (int) $request->year;
        $reportData = $this->generateYearlyReportData($request, $year);

        return response()->json($reportData);
    }

    /**
     * Export yearly OEE report as Excel
     */
    public function exportYearlyExcel(Request $request)
    {
        // Check if user is admin or has reports.view permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('reports.view')) {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'plant_id' => 'nullable|exists:plants,id',
            'line_id' => 'nullable|exists:lines,id',
            'machine_id' => 'nullable|exists:machines,id',
        ]);

        $year = (int) $request->year;
        $reportData = $this->generateYearlyReportData($request, $year);

        return \Excel::download(
            new \App\Exports\YearlyOeeReportExport($reportData, $year),
            'yearly-oee-report-' . $year . '.xlsx'
        );
    }

    /**
     * Helper method to generate yearly report data
     */
    private function generateYearlyReportData(Request $request, int $year): array
    {
        $user = $request->user();
        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = Carbon::create($year, 12, 31)->endOfYear();

        // Build machine query based on filters
        $machineQuery = Machine::with(['line.plant', 'machineProductConfigs.product']);

        if ($request->machine_id) {
            $machineQuery->where('id', $request->machine_id);
        } elseif ($request->line_id) {
            $machineQuery->where('line_id', $request->line_id);
        } elseif ($request->plant_id) {
            $machineQuery->whereHas('line', function($q) use ($request) {
                $q->where('plant_id', $request->plant_id);
            });
        }

        // Apply permission scope
        if (!$user->isAdmin()) {
            $allowedPlantIds = $user->plants()->pluck('id');
            $machineQuery->whereHas('line', function($q) use ($allowedPlantIds) {
                $q->whereIn('plant_id', $allowedPlantIds);
            });
        }

        $machines = $machineQuery->get();
        $machineData = [];

        foreach ($machines as $machine) {
            // Get metrics for this machine for the whole year
            $metrics = DailyOeeMetric::where('machine_id', $machine->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            // Calculate monthly OEE averages
            $monthlyOee = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
                $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();
                
                $monthMetrics = $metrics->filter(function($m) use ($monthStart, $monthEnd) {
                    $date = Carbon::parse($m->date);
                    return $date->between($monthStart, $monthEnd);
                });

                $monthlyOee['month_' . $month] = $monthMetrics->count() > 0 
                    ? $monthMetrics->avg('oee_score') 
                    : null;
            }

            // Calculate yearly aggregates
            $totalRunTime = $metrics->sum('total_run_time');
            $totalPlannedTime = $metrics->sum('total_planned_production_time');
            $totalDowntime = $metrics->sum('total_downtime');
            $totalReject = $metrics->sum('total_reject');
            $totalMaterialLoss = $metrics->sum('total_material_loss') ?? 0;

            // Get target OEE for this machine
            $target = \App\Models\ProductionTarget::getApplicableTarget($machine->id);
            
            // Get UoM from product config
            $productConfig = $machine->machineProductConfigs->first();
            $uom = $productConfig?->product?->unit_of_measure ?? $productConfig?->product?->finished_unit ?? 'units';

            $machineData[] = [
                'id' => $machine->id,
                'name' => $machine->name,
                'line_name' => $machine->line->name ?? 'N/A',
                'plant_name' => $machine->line->plant->name ?? 'N/A',
                'target_oee' => $target?->target_oee,
                'monthly_oee' => $monthlyOee,
                'availability' => $metrics->count() > 0 ? $metrics->avg('availability_score') : null,
                'utilization' => $totalPlannedTime > 0 
                    ? ($totalRunTime / $totalPlannedTime) * 100 
                    : null,
                'quality' => $metrics->count() > 0 ? $metrics->avg('quality_score') : null,
                'time_lost_hours' => $totalDowntime / 3600, // Convert seconds to hours
                'total_waste' => $totalReject + $totalMaterialLoss,
                'uom' => $uom,
            ];
        }

        return [
            'year' => $year,
            'machines' => $machineData,
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
