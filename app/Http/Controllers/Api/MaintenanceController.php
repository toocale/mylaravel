<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceLog;
use App\Models\MachineComponent;
use App\Models\SparePart;
use App\Models\SparePartUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    // Helper to check permissions
    private function checkPerms($machineId)
    {
        $machine = Machine::with('line')->findOrFail($machineId);
        if (!auth()->user()->hasPermission('maintenance.view') && !auth()->user()->canManagePlant($machine->line->plant_id)) {
            abort(403, 'Unauthorized to view maintenance data.');
        }
        return $machine; // Return machine to avoid re-querying
    }

    // ========== MAINTENANCE SCHEDULES ==========
    
    /**
     * Get all maintenance schedules for a machine
     */
    public function getSchedules($machineId)
    {
        $this->checkPerms($machineId);

        $schedules = MaintenanceSchedule::where('machine_id', $machineId)
            ->with(['assignedTo:id,name', 'machine:id,name'])
            ->active()
            ->orderBy('next_due_at')
            ->get();

        return response()->json($schedules);
    }

    /**
     * Get overdue maintenance tasks for a machine
     */
    public function getOverdue($machineId)
    {
        $this->checkPerms($machineId);

        $overdue = MaintenanceSchedule::where('machine_id', $machineId)
            ->overdue()
            ->active()
            ->with(['assignedTo:id,name'])
            ->orderBy('priority', 'desc')
            ->orderBy('next_due_at')
            ->get();

        return response()->json([
            'count' => $overdue->count(),
            'tasks' => $overdue
        ]);
    }

    /**
     * Get upcoming maintenance tasks
     */
    public function getUpcoming($machineId, Request $request)
    {
        $this->checkPerms($machineId);
        
        $days = $request->input('days', 7);
        
        $upcoming = MaintenanceSchedule::where('machine_id', $machineId)
            ->upcoming($days)
            ->active()
            ->with(['assignedTo:id,name'])
            ->orderBy('next_due_at')
            ->get();

        return response()->json([
            'days' => $days,
            'count' => $upcoming->count(),
            'tasks' => $upcoming
        ]);
    }

    /**
     * Create a new maintenance schedule
     */
    public function createSchedule(Request $request, $machineId)
    {
        $this->checkPerms($machineId);

        if (!$request->user()->hasPermission('maintenance.create')) {
            abort(403, 'Unauthorized to create maintenance schedules.');
        }
        
        $validated = $request->validate([
            'task_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'maintenance_type' => 'required|in:daily,weekly,monthly,quarterly,annual,conditional',
            'frequency_days' => 'nullable|integer|min:1',
            'frequency_hours' => 'nullable|integer|min:1',
            'frequency_cycles' => 'nullable|integer|min:1',
            'next_due_at' => 'nullable|date',
            'priority' => 'required|in:low,medium,high,critical',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        $validated['machine_id'] = $machineId;

        // If no next_due_at provided, calculate based on frequency
        if (!isset($validated['next_due_at']) && isset($validated['frequency_days'])) {
            $validated['next_due_at'] = now()->addDays($validated['frequency_days']);
        }

        $schedule = MaintenanceSchedule::create($validated);
        $schedule->load('assignedTo', 'machine');

        return response()->json($schedule, 201);
    }

    /**
     * Update a maintenance schedule
     */
    public function updateSchedule(Request $request, $scheduleId)
    {
        if (!$request->user()->hasPermission('maintenance.edit')) {
            abort(403, 'Unauthorized to edit maintenance schedules.');
        }
        $schedule = MaintenanceSchedule::findOrFail($scheduleId);

        $validated = $request->validate([
            'task_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'maintenance_type' => 'sometimes|required|in:daily,weekly,monthly,quarterly,annual,conditional',
            'frequency_days' => 'nullable|integer|min:1',
            'frequency_hours' => 'nullable|integer|min:1',
            'frequency_cycles' => 'nullable|integer|min:1',
            'next_due_at' => 'nullable|date',
            'priority' => 'sometimes|required|in:low,medium,high,critical',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $schedule->update($validated);
        $schedule->load('assignedTo', 'machine');

        return response()->json($schedule);
    }

    /**
     * Delete a maintenance schedule
     */
    public function deleteSchedule(Request $request, $scheduleId)
    {
        if (!$request->user()->hasPermission('maintenance.delete')) {
            abort(403, 'Unauthorized to delete maintenance schedules.');
        }
        $schedule = MaintenanceSchedule::findOrFail($scheduleId);
        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully']);
    }

    // ========== MAINTENANCE LOGS ==========

    /**
     * Get maintenance history for a machine
     */
    public function getHistory($machineId, Request $request)
    {
        $this->checkPerms($machineId);
        
        $days = $request->input('days', 30);

        $logs = MaintenanceLog::where('machine_id', $machineId)
            ->recent($days)
            ->with(['performedBy:id,name', 'maintenanceSchedule:id,task_name'])
            ->orderBy('performed_at', 'desc')
            ->get();

        return response()->json([
            'days' => $days,
            'count' => $logs->count(),
            'logs' => $logs
        ]);
    }

    /**
     * Log completed maintenance
     */
    public function logMaintenance(Request $request, $machineId)
    {
        $this->checkPerms($machineId);

        if (!$request->user()->hasPermission('maintenance.create')) {
            abort(403, 'Unauthorized to log maintenance.');
        }
        
        $validated = $request->validate([
            'maintenance_schedule_id' => 'nullable|exists:maintenance_schedules,id',
            'performed_at' => 'required|date',
            'task_description' => 'required|string',
            'duration_minutes' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'parts_replaced' => 'nullable|array',
            'parts_replaced.*.name' => 'required|string',
            'parts_replaced.*.quantity' => 'required|integer|min:1',
            'parts_replaced.*.cost' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $validated['machine_id'] = $machineId;
        $validated['performed_by_user_id'] = auth()->id();

        // If this was scheduled maintenance, update the schedule
        if (isset($validated['maintenance_schedule_id'])) {
            $schedule = MaintenanceSchedule::find($validated['maintenance_schedule_id']);
            
            if ($schedule) {
                $schedule->last_performed_at = $validated['performed_at'];
                
                // Calculate next due date based on frequency
                if ($schedule->frequency_days) {
                    $schedule->next_due_at = now()->addDays($schedule->frequency_days);
                    $validated['next_scheduled_at'] = $schedule->next_due_at;
                } elseif ($schedule->frequency_hours) {
                    $schedule->next_due_at = now()->addHours($schedule->frequency_hours);
                    $validated['next_scheduled_at'] = $schedule->next_due_at;
                }
                
                $schedule->is_overdue = false;
                $schedule->save();
            }
        }

        $log = MaintenanceLog::create($validated);
        $log->load('performedBy', 'maintenanceSchedule', 'machine');

        return response()->json($log, 201);
    }

    /**
     * Get specific maintenance log details
     */
    public function getLog($logId)
    {
        $log = MaintenanceLog::with(['performedBy', 'maintenanceSchedule', 'machine'])
            ->findOrFail($logId);

        return response()->json($log);
    }

    // ========== MACHINE COMPONENTS ==========

    /**
     * Get all components for a machine
     */
    public function getComponents($machineId)
    {
        $this->checkPerms($machineId);
        
        $components = MachineComponent::where('machine_id', $machineId)
            ->orderBy('status', 'desc') // Critical first
            ->orderBy('component_name')
            ->get()
            ->map(function ($component) {
                $component->remaining_life_percentage = $component->getRemainingLifePercentage();
                return $component;
            });

        return response()->json($components);
    }

    /**
     * Get component health summary
     */
    public function getComponentHealth($machineId)
    {
        $this->checkPerms($machineId);
        
        $components = MachineComponent::where('machine_id', $machineId)->get();

        $summary = [
            'total' => $components->count(),
            'good' => $components->where('status', 'good')->count(),
            'warning' => $components->where('status', 'warning')->count(),
            'critical' => $components->where('status', 'critical')->count(),
            'needs_attention' => $components->whereIn('status', ['warning', 'critical'])->values(),
        ];

        return response()->json($summary);
    }

    /**
     * Add a new component
     */
    public function addComponent(Request $request, $machineId)
    {
        $this->checkPerms($machineId);

        if (!$request->user()->hasPermission('maintenance.create')) {
            abort(403, 'Unauthorized to add components.');
        }
        
        $validated = $request->validate([
            'component_name' => 'required|string|max:255',
            'component_type' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'installed_at' => 'nullable|date',
            'expected_lifespan_hours' => 'nullable|integer|min:1',
            'current_runtime_hours' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $validated['machine_id'] = $machineId;
        $validated['status'] = 'good';

        $component = MachineComponent::create($validated);
        
        // Auto-calculate initial status
        if ($component->expected_lifespan_hours) {
            $component->updateStatus();
        }

        return response()->json($component, 201);
    }

    /**
     * Update a component
     */
    public function updateComponent(Request $request, $componentId)
    {
        if (!$request->user()->hasPermission('maintenance.edit')) {
            abort(403, 'Unauthorized to edit components.');
        }
        $component = MachineComponent::findOrFail($componentId);

        $validated = $request->validate([
            'component_name' => 'sometimes|required|string|max:255',
            'component_type' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'installed_at' => 'nullable|date',
            'expected_lifespan_hours' => 'nullable|integer|min:1',
            'current_runtime_hours' => 'nullable|integer|min:0',
            'status' => 'sometimes|in:good,warning,critical,replaced',
            'last_inspected_at' => 'nullable|date',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $component->update($validated);

        // Re-calculate status if runtime changed
        if (isset($validated['current_runtime_hours']) || isset($validated['expected_lifespan_hours'])) {
            $component->updateStatus();
        }

        return response()->json($component);
    }

    /**
     * Delete a component
     */
    public function deleteComponent(Request $request, $componentId)
    {
        if (!$request->user()->hasPermission('maintenance.delete')) {
            abort(403, 'Unauthorized to delete components.');
        }
        $component = MachineComponent::findOrFail($componentId);
        $component->delete();

        return response()->json(['message' => 'Component deleted successfully']);
    }

    // ========== HEALTH METRICS ==========

    /**
     * Get overall health dashboard data for a machine
     */
    public function getHealthDashboard($machineId)
    {
        $machine = $this->checkPerms($machineId);
        // $machine = Machine::findOrFail($machineId); // checkPerms finds it

        // Get overdue maintenance count
        $overdueCount = MaintenanceSchedule::where('machine_id', $machineId)
            ->overdue()
            ->active()
            ->count();

        // Get upcoming maintenance (next 7 days)
        $upcomingCount = MaintenanceSchedule::where('machine_id', $machineId)
            ->upcoming(7)
            ->active()
            ->count();

        // Get components needing attention
        $criticalComponents = MachineComponent::where('machine_id', $machineId)
            ->needsAttention()
            ->count();

        // Get recent maintenance count (last 30 days)
        $recentMaintenanceCount = MaintenanceLog::where('machine_id', $machineId)
            ->recent(30)
            ->count();

        // Calculate MTBF and MTTR (simplified for now)
        $mtbf = $this->calculateMTBF($machineId);
        $mttr = $this->calculateMTTR($machineId);

        // Maintenance compliance rate
        $complianceRate = $this->calculateComplianceRate($machineId);

        return response()->json([
            'machine_id' => $machineId,
            'machine_name' => $machine->name,
            'overdue_tasks' => $overdueCount,
            'upcoming_tasks' => $upcomingCount,
            'critical_components' => $criticalComponents,
            'recent_maintenance' => $recentMaintenanceCount,
            'mtbf_hours' => $mtbf,
            'mttr_minutes' => $mttr,
            'compliance_rate' => $complianceRate,
        ]);
    }

    /**
     * Calculate MTBF (Mean Time Between Failures)
     * Simplified version - calculates from downtime events
     */
    private function calculateMTBF($machineId)
    {
        // Get unplanned downtime events from last 90 days
        $downtimes = DB::table('downtime_events')
            ->join('reason_codes', 'downtime_events.reason_code_id', '=', 'reason_codes.id')
            ->where('downtime_events.machine_id', $machineId)
            ->where('reason_codes.category', 'unplanned')
            ->where('downtime_events.start_time', '>=', now()->subDays(90))
            ->count();

        if ($downtimes == 0) {
            return null; // No failures
        }

        // Assume 24/7 operation for 90 days = 2160 hours
        $totalHours = 90 * 24;
        return round($totalHours / $downtimes, 1);
    }

    /**
     * Calculate MTTR (Mean Time To Repair)
     */
    private function calculateMTTR($machineId)
    {
        // Average duration of maintenance logs
        $avgDuration = MaintenanceLog::where('machine_id', $machineId)
            ->recent(90)
            ->whereNotNull('duration_minutes')
            ->avg('duration_minutes');

        return $avgDuration ? round($avgDuration, 1) : null;
    }

    /**
     * Calculate maintenance compliance rate
     */
    private function calculateComplianceRate($machineId)
    {
        $totalScheduled = MaintenanceSchedule::where('machine_id', $machineId)
            ->active()
            ->count();

        if ($totalScheduled == 0) {
            return 100; // No schedules means 100% compliance
        }

        $overdue = MaintenanceSchedule::where('machine_id', $machineId)
            ->overdue()
            ->active()
            ->count();

        $onTime = $totalScheduled - $overdue;
        return round(($onTime / $totalScheduled) * 100, 1);
    }

    /**
     * Sync component runtime from historical shift data
     */
    public function syncComponentRuntime($machineId)
    {
        $components = MachineComponent::where('machine_id', $machineId)
            ->whereNotIn('status', ['replaced', 'removed'])
            ->get();

        if ($components->isEmpty()) {
            return response()->json([
                'message' => 'No active components found',
                'updated' => 0
            ]);
        }

        $earliestInstall = $components->min('installed_at');
        
        $shifts = \App\Models\ProductionShift::where('machine_id', $machineId)
            ->where('status', 'completed')
            ->whereNotNull('ended_at')
            ->when($earliestInstall, function ($query) use ($earliestInstall) {
                return $query->where('ended_at', '>=', $earliestInstall);
            })
            ->get();

        $updated = 0;
        $totalHours = 0;

        foreach ($components as $component) {
            $componentShifts = $shifts->filter(function ($shift) use ($component) {
                return $shift->ended_at >= $component->installed_at;
            });

            $componentHours = $componentShifts->sum(function ($shift) {
                return $shift->getDurationHours() ?? 0;
            });

            $component->current_runtime_hours = round($componentHours, 2);
            $component->save();

            $updated++;
            $totalHours += $componentHours;
        }

        return response()->json([
            'message' => 'Runtime synced successfully',
            'updated' => $updated,
            'shifts_processed' => $shifts->count(),
            'total_hours' => round($totalHours, 2)
        ]);
    }

    /**
     * Export schedules to PDF using DomPDF (same as OEE reports)
     */
    public function exportSchedulesPdf($machineId)
    {
        $schedules = MaintenanceSchedule::where('machine_id', $machineId)
            ->with(['assignedTo:id,name'])
            ->active()
            ->orderBy('next_due_at')
            ->get();

        $machine = \App\Models\Machine::find($machineId);

        // Get site settings for PDF header (same as OEE reports)
        $siteName = \App\Models\SiteSetting::get('site_name', null);
        $siteLogo = \App\Models\SiteSetting::get('site_logo', null);
        
        // Fallback to config if site settings don't exist
        if (!$siteName) {
            $siteName = config('app.name', 'OEE System');
        }
        
        // Convert logo path to absolute path if it exists
        $logoPath = null;
        if ($siteLogo) {
            $siteLogo = ltrim($siteLogo, '/');
            
            if (\Storage::disk('public')->exists($siteLogo)) {
                $logoPath = storage_path('app/public/' . $siteLogo);
            } else {
                $publicPath = public_path($siteLogo);
                if (file_exists($publicPath)) {
                    $logoPath = $publicPath;
                }
            }
        }

        $html = view('reports.maintenance-schedules-pdf', [
            'schedules' => $schedules,
            'machine' => $machine,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'site_name' => $siteName,
            'site_logo' => $logoPath,
        ])->render();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        
        return $pdf->download('maintenance-schedules-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export schedules to Excel/CSV
     */
    public function exportSchedulesExcel($machineId)
    {
        $schedules = MaintenanceSchedule::where('machine_id', $machineId)
            ->with(['assignedTo:id,name'])
            ->active()
            ->orderBy('next_due_at')
            ->get();

        $csv = "Task,Type,Priority,Frequency,Next Due,Last Performed,Assigned To\n";
        foreach ($schedules as $schedule) {
            $csv .= "\"{$schedule->task_name}\",";
            $csv .= "\"{$schedule->maintenance_type}\",";
            $csv .= "\"{$schedule->priority}\",";
            $csv .= "\"{$schedule->frequency_days} days\",";
            $csv .= "\"{$schedule->next_due_at}\",";
            $csv .= "\"" . ($schedule->last_performed_at ?: 'Never') . "\",";
            $csv .= "\"" . ($schedule->assignedTo->name ?? 'Unassigned') . "\"\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="schedules.csv"');
    }

    /**
     * Export maintenance history to Excel
     */
    public function exportHistory($machineId)
    {
        $logs = MaintenanceLog::where('machine_id', $machineId)
            ->with(['performedBy:id,name'])
            ->orderBy('performed_at', 'desc')
            ->get();

        $csv = "Date,Task,Duration (min),Cost,Performed By,Notes\n";
        foreach ($logs as $log) {
            $csv .= "\"{$log->performed_at}\",";
            $csv .= "\"{$log->task_description}\",";
            $csv .= "\"{$log->duration_minutes}\",";
            $csv .= "\"{$log->cost}\",";
            $csv .= "\"" . ($log->performedBy->name ?? 'Unknown') . "\",";
            $csv .= "\"" . str_replace('"', '""', $log->notes ?? '') . "\"\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="maintenance-history.csv"');
    }

    // ========== SPARE PARTS INVENTORY ==========

    /**
     * Get all spare parts for a machine (includes global parts)
     */
    public function getSpareParts($machineId = null, Request $request)
    {
        if ($machineId) {
            $this->checkPerms($machineId);
        }

        $query = SparePart::active();
        
        if ($machineId) {
            $query->forMachine($machineId);
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('low_stock')) {
            $query->lowStock();
        }

        $parts = $query->with('machine:id,name')
            ->orderBy('name')
            ->get()
            ->map(function ($part) {
                $part->stock_status = $part->getStockStatus();
                $part->total_usage = $part->getTotalUsageCount();
                return $part;
            });

        return response()->json([
            'count' => $parts->count(),
            'parts' => $parts,
            'low_stock_count' => $parts->where('stock_status', 'low_stock')->count(),
            'out_of_stock_count' => $parts->where('stock_status', 'out_of_stock')->count(),
        ]);
    }

    /**
     * Get all low stock spare parts across all machines
     */
    public function getLowStockParts()
    {
        $parts = SparePart::active()
            ->lowStock()
            ->with('machine:id,name')
            ->orderBy('quantity_in_stock')
            ->get()
            ->map(function ($part) {
                $part->stock_status = $part->getStockStatus();
                return $part;
            });

        return response()->json([
            'count' => $parts->count(),
            'parts' => $parts
        ]);
    }

    /**
     * Create a new spare part
     */
    public function createSparePart(Request $request, $machineId = null)
    {
        if (!$request->user()->hasPermission('maintenance.create')) {
            abort(403, 'Unauthorized to create spare parts.');
        }

        $validated = $request->validate([
            'part_number' => 'required|string|max:100|unique:spare_parts,part_number',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'quantity_in_stock' => 'required|integer|min:0',
            'minimum_stock_level' => 'required|integer|min:0',
            'reorder_quantity' => 'nullable|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
        ]);

        $validated['machine_id'] = $machineId;

        $part = SparePart::create($validated);
        $part->stock_status = $part->getStockStatus();

        return response()->json($part, 201);
    }

    /**
     * Update a spare part
     */
    public function updateSparePart(Request $request, $partId)
    {
        if (!$request->user()->hasPermission('maintenance.edit')) {
            abort(403, 'Unauthorized to edit spare parts.');
        }

        $part = SparePart::findOrFail($partId);

        $validated = $request->validate([
            'part_number' => 'sometimes|required|string|max:100|unique:spare_parts,part_number,' . $partId,
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'quantity_in_stock' => 'sometimes|required|integer|min:0',
            'minimum_stock_level' => 'sometimes|required|integer|min:0',
            'reorder_quantity' => 'nullable|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $part->update($validated);
        $part->stock_status = $part->getStockStatus();

        return response()->json($part);
    }

    /**
     * Delete a spare part
     */
    public function deleteSparePart(Request $request, $partId)
    {
        if (!$request->user()->hasPermission('maintenance.delete')) {
            abort(403, 'Unauthorized to delete spare parts.');
        }

        $part = SparePart::findOrFail($partId);
        $part->delete();

        return response()->json(['message' => 'Spare part deleted successfully']);
    }

    /**
     * Record spare part usage (during maintenance)
     */
    public function recordPartUsage(Request $request, $machineId)
    {
        $this->checkPerms($machineId);

        if (!$request->user()->hasPermission('maintenance.create')) {
            abort(403, 'Unauthorized to record part usage.');
        }

        $validated = $request->validate([
            'spare_part_id' => 'required|exists:spare_parts,id',
            'maintenance_log_id' => 'nullable|exists:maintenance_logs,id',
            'quantity_used' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $part = SparePart::findOrFail($validated['spare_part_id']);

        // Check if enough stock
        if ($part->quantity_in_stock < $validated['quantity_used']) {
            return response()->json([
                'message' => 'Insufficient stock. Available: ' . $part->quantity_in_stock,
                'available' => $part->quantity_in_stock
            ], 422);
        }

        // Record usage
        $usage = SparePartUsage::create([
            'spare_part_id' => $validated['spare_part_id'],
            'maintenance_log_id' => $validated['maintenance_log_id'] ?? null,
            'machine_id' => $machineId,
            'used_by_user_id' => auth()->id(),
            'quantity_used' => $validated['quantity_used'],
            'cost_at_use' => $part->unit_cost,
            'used_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        // Decrement stock
        $part->decrementStock($validated['quantity_used']);

        $usage->load('sparePart', 'usedBy');

        return response()->json([
            'usage' => $usage,
            'new_stock_level' => $part->fresh()->quantity_in_stock,
            'is_low_stock' => $part->fresh()->isLowStock(),
        ], 201);
    }

    /**
     * Get usage history for a spare part
     */
    public function getPartUsageHistory($partId, Request $request)
    {
        $part = SparePart::findOrFail($partId);
        $days = $request->input('days', 90);

        $usages = SparePartUsage::where('spare_part_id', $partId)
            ->recent($days)
            ->with(['usedBy:id,name', 'machine:id,name', 'maintenanceLog:id,task_description'])
            ->orderBy('used_at', 'desc')
            ->get();

        return response()->json([
            'part' => $part,
            'days' => $days,
            'usages' => $usages,
            'total_used' => $usages->sum('quantity_used'),
            'total_cost' => $usages->sum(fn($u) => $u->getTotalCost()),
        ]);
    }

    /**
     * Adjust stock (for inventory corrections)
     */
    public function adjustStock(Request $request, $partId)
    {
        if (!$request->user()->hasPermission('maintenance.edit')) {
            abort(403, 'Unauthorized to adjust stock.');
        }

        $validated = $request->validate([
            'adjustment' => 'required|integer', // Can be positive or negative
            'reason' => 'required|string|max:255',
        ]);

        $part = SparePart::findOrFail($partId);
        $newQuantity = $part->quantity_in_stock + $validated['adjustment'];

        if ($newQuantity < 0) {
            return response()->json([
                'message' => 'Cannot adjust stock below zero.',
                'current_stock' => $part->quantity_in_stock
            ], 422);
        }

        $part->quantity_in_stock = $newQuantity;
        $part->save();

        return response()->json([
            'message' => 'Stock adjusted successfully',
            'previous_stock' => $part->quantity_in_stock - $validated['adjustment'],
            'new_stock' => $part->quantity_in_stock,
            'adjustment' => $validated['adjustment'],
            'reason' => $validated['reason'],
        ]);
    }

    /**
     * Get spare part categories (for dropdowns)
     */
    public function getPartCategories()
    {
        $categories = SparePart::active()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return response()->json($categories);
    }

    // ========== CALENDAR INTEGRATION ==========

    /**
     * Get maintenance schedules as calendar events (FullCalendar format)
     */
    public function getCalendarEvents($machineId, Request $request)
    {
        $this->checkPerms($machineId);

        $start = $request->input('start', now()->startOfMonth()->format('Y-m-d'));
        $end = $request->input('end', now()->endOfMonth()->addMonth()->format('Y-m-d'));

        $schedules = MaintenanceSchedule::where('machine_id', $machineId)
            ->active()
            ->where(function($query) use ($start, $end) {
                $query->whereBetween('next_due_at', [$start, $end])
                      ->orWhere('is_overdue', true);
            })
            ->with(['assignedTo:id,name', 'machine:id,name'])
            ->get();

        // Transform to FullCalendar event format
        $events = $schedules->map(function ($schedule) {
            return [
                'id' => $schedule->id,
                'title' => $schedule->task_name,
                'start' => $schedule->next_due_at?->format('Y-m-d'),
                'end' => $schedule->next_due_at?->format('Y-m-d'),
                'allDay' => true,
                'backgroundColor' => $this->getPriorityColor($schedule->priority, $schedule->is_overdue),
                'borderColor' => $this->getPriorityColor($schedule->priority, $schedule->is_overdue),
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'description' => $schedule->description,
                    'priority' => $schedule->priority,
                    'maintenance_type' => $schedule->maintenance_type,
                    'assigned_to' => $schedule->assignedTo?->name,
                    'estimated_duration' => $schedule->estimated_duration_minutes,
                    'is_overdue' => $schedule->is_overdue,
                    'last_performed' => $schedule->last_performed_at?->format('Y-m-d'),
                ],
            ];
        });

        // Also get maintenance logs for history display
        $logs = MaintenanceLog::where('machine_id', $machineId)
            ->whereBetween('performed_at', [$start, $end])
            ->with('performedBy:id,name')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => 'log_' . $log->id,
                    'title' => '✓ ' . $log->task_description,
                    'start' => $log->performed_at->format('Y-m-d'),
                    'end' => $log->performed_at->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => '#10b981', // Green for completed
                    'borderColor' => '#10b981',
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'type' => 'completed',
                        'duration' => $log->duration_minutes,
                        'performed_by' => $log->performedBy?->name,
                        'notes' => $log->notes,
                    ],
                ];
            });

        return response()->json([
            'events' => $events->merge($logs)->values(),
            'scheduled_count' => $schedules->count(),
            'completed_count' => $logs->count(),
        ]);
    }

    /**
     * Get priority color for calendar events
     */
    private function getPriorityColor(string $priority, bool $isOverdue = false): string
    {
        if ($isOverdue) {
            return '#dc2626'; // Red for overdue
        }

        return match($priority) {
            'critical' => '#dc2626', // Red
            'high' => '#ea580c',     // Orange
            'medium' => '#ca8a04',   // Yellow
            'low' => '#16a34a',      // Green
            default => '#6b7280',    // Gray
        };
    }

    /**
     * Update schedule date (for drag-and-drop reschedule)
     */
    public function rescheduleTask(Request $request, $scheduleId)
    {
        if (!$request->user()->hasPermission('maintenance.edit')) {
            abort(403, 'Unauthorized to reschedule tasks.');
        }

        $schedule = MaintenanceSchedule::findOrFail($scheduleId);

        $validated = $request->validate([
            'next_due_at' => 'required|date',
        ]);

        $schedule->next_due_at = $validated['next_due_at'];
        
        // Update overdue status based on new date
        $schedule->is_overdue = now()->startOfDay()->gt($schedule->next_due_at);
        
        $schedule->save();

        return response()->json([
            'message' => 'Task rescheduled successfully',
            'schedule' => $schedule,
        ]);
    }

    /**
     * Export schedules as iCal (.ics) file
     */
    public function exportIcal($machineId)
    {
        $this->checkPerms($machineId);

        $machine = Machine::findOrFail($machineId);
        $schedules = MaintenanceSchedule::where('machine_id', $machineId)
            ->active()
            ->orderBy('next_due_at')
            ->get();

        $siteName = \App\Models\SiteSetting::get('site_name', config('app.name', 'OEE System'));
        
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-/{$siteName}//Maintenance//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:Maintenance - {$machine->name}\r\n";

        foreach ($schedules as $schedule) {
            if (!$schedule->next_due_at) continue;

            $uid = 'maintenance-' . $schedule->id . '@' . request()->getHost();
            $dtStamp = now()->format('Ymd\THis\Z');
            $dtStart = $schedule->next_due_at->format('Ymd');
            $dtEnd = $schedule->next_due_at->addDay()->format('Ymd');

            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:{$uid}\r\n";
            $ical .= "DTSTAMP:{$dtStamp}\r\n";
            $ical .= "DTSTART;VALUE=DATE:{$dtStart}\r\n";
            $ical .= "DTEND;VALUE=DATE:{$dtEnd}\r\n";
            $ical .= "SUMMARY:[{$schedule->priority}] {$schedule->task_name}\r\n";
            
            $description = "Machine: {$machine->name}\\n";
            $description .= "Type: {$schedule->maintenance_type}\\n";
            if ($schedule->description) {
                $description .= "Details: {$schedule->description}\\n";
            }
            if ($schedule->estimated_duration_minutes) {
                $description .= "Est. Duration: {$schedule->estimated_duration_minutes} min";
            }
            
            $ical .= "DESCRIPTION:" . str_replace("\n", "\\n", $description) . "\r\n";
            $ical .= "CATEGORIES:Maintenance,{$schedule->maintenance_type}\r\n";
            
            // Priority mapping: 1-4 = High, 5 = Medium, 6-9 = Low
            $icalPriority = match($schedule->priority) {
                'critical' => 1,
                'high' => 3,
                'medium' => 5,
                'low' => 7,
                default => 5,
            };
            $ical .= "PRIORITY:{$icalPriority}\r\n";
            
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR\r\n";

        return response($ical)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="maintenance-' . $machine->name . '.ics"');
    }
}

