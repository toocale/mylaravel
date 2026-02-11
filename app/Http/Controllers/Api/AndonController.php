<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Machine;
use App\Models\ProductionShift;
use App\Models\DowntimeEvent;
use App\Services\OeeCalculationService;
use Illuminate\Http\Request;

class AndonController extends Controller
{
    protected OeeCalculationService $oeeService;

    public function __construct(OeeCalculationService $oeeService)
    {
        $this->oeeService = $oeeService;
    }

    /**
     * GET /api/v1/andon/status
     * Returns all machines with live status for the Andon board.
     */
    public function status(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Andon API Hit: status endpoint', ['plant_id' => $request->input('plant_id')]);
        try {

        $plantId = $request->input('plant_id');

        $query = Machine::with(['line.plant']);

        if ($plantId) {
            $query->whereHas('line', fn($q) => $q->where('plant_id', $plantId));
        }

        $machines = $query->get();

        $statuses = $machines->map(function ($machine) {
            $activeShift = ProductionShift::where('machine_id', $machine->id)
                ->where('status', 'active')
                ->with(['product', 'user', 'shift', 'productChangeovers.toProduct'])
                ->first();

            $activeDowntime = null;
            $status = 'idle'; // Default: no active shift
            $currentOee = null;
            $currentProduct = null;
            $throughput = null;
            $downtimeMinutes = 0;
            $downtimeReason = null;
            $shiftInfo = null;

            if ($activeShift) {
                // Check for active (unresolved) downtime
                $activeDowntime = DowntimeEvent::where('production_shift_id', $activeShift->id)
                    ->whereNull('ended_at')
                    ->with('downtimeType')
                    ->first();

                if ($activeDowntime) {
                    $status = 'stopped';
                    $downtimeMinutes = (int) $activeDowntime->started_at->diffInMinutes(now());
                    $downtimeReason = $activeDowntime->downtimeType?->name ?? $activeDowntime->reason ?? 'Unknown';
                } else {
                    // Running — check performance
                    $status = 'running';
                }

                // Calculate live OEE
                $currentOee = $this->calculateQuickOee($activeShift);

                // Determine current product (accounting for changeovers)
                $currentProduct = $activeShift->product?->name;
                $lastChangeover = $activeShift->productChangeovers->sortBy('changed_at')->last();
                if ($lastChangeover && $lastChangeover->toProduct) {
                    $currentProduct = $lastChangeover->toProduct->name;
                }
                $shiftInfo = [
                    'name' => $activeShift->shift?->name,
                    'started_at' => $activeShift->started_at?->format('H:i'),
                    'operator' => $activeShift->user?->name,
                ];

                // Throughput: counts per hour
                $hoursRunning = max(0.1, $activeShift->started_at->diffInMinutes(now()) / 60);
                $throughput = round(($activeShift->total_count ?? 0) / $hoursRunning);
            }

            // Count active alerts for this machine
            $activeAlerts = Alert::where('machine_id', $machine->id)
                ->active()
                ->count();

            return [
                'id' => $machine->id,
                'name' => $machine->name,
                'line_id' => $machine->line_id,
                'line_name' => $machine->line?->name,
                'plant_id' => $machine->line?->plant_id,
                'plant_name' => $machine->line?->plant?->name,
                'status' => $status,
                'oee' => $currentOee,
                'product' => $currentProduct,
                'throughput' => $throughput,
                'good_count' => $activeShift?->good_count ?? 0,
                'reject_count' => $activeShift?->reject_count ?? 0,
                'total_count' => $activeShift?->total_count ?? 0,
                'downtime_minutes' => $downtimeMinutes,
                'downtime_reason' => $downtimeReason,
                'shift' => $shiftInfo,
                'active_alerts' => $activeAlerts,
            ];
        });

        // Group by plant
        $grouped = $statuses->groupBy('plant_name');

        // Summary counts
        $summary = [
            'total' => $statuses->count(),
            'running' => $statuses->where('status', 'running')->count(),
            'stopped' => $statuses->where('status', 'stopped')->count(),
            'idle' => $statuses->where('status', 'idle')->count(),
        ];

        return response()->json([
            'machines' => $statuses,
            'grouped' => $grouped,
            'summary' => $summary,
        ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Andon API Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Quick OEE calculation for a live shift (simplified).
     */
    private function calculateQuickOee(ProductionShift $shift): ?float
    {
        $totalMinutes = max(1, $shift->started_at->diffInMinutes(now()));

        // Downtime minutes
        $downtimeMinutes = DowntimeEvent::where('production_shift_id', $shift->id)
            ->selectRaw("SUM(CASE WHEN end_time IS NOT NULL THEN (strftime('%s', end_time) - strftime('%s', start_time)) / 60 ELSE (strftime('%s', 'now') - strftime('%s', start_time)) / 60 END) as total")
            ->value('total') ?? 0;

        $runTime = max(1, $totalMinutes - $downtimeMinutes);

        // Availability
        $availability = ($totalMinutes - $downtimeMinutes) / $totalMinutes;

        // Performance (need ideal rate)
        $totalCount = $shift->total_count ?? 0;
        $idealRate = $this->getIdealRate($shift->machine_id, $shift->product_id);
        $idealCount = ($idealRate / 60) * $runTime; // ideal per minute * runTime
        $performance = $idealCount > 0 ? min(1, $totalCount / $idealCount) : 0;

        // Quality
        $goodCount = $shift->good_count ?? 0;
        $quality = $totalCount > 0 ? $goodCount / $totalCount : 1;

        $oee = round($availability * $performance * $quality * 100, 1);

        return max(0, min(100, $oee));
    }

    private function getIdealRate(?int $machineId, ?int $productId): float
    {
        if (!$machineId) return 100;

        if ($productId) {
            $config = \App\Models\MachineProductConfig::where('machine_id', $machineId)
                ->where('product_id', $productId)
                ->first();
            if ($config && $config->ideal_rate > 0) {
                return $config->ideal_rate;
            }
        }

        $machine = Machine::find($machineId);
        return $machine?->default_ideal_rate ?? 100;
    }

    // ==================== ALERTS ====================

    /**
     * GET /api/v1/andon/alerts
     */
    public function alerts(Request $request)
    {
        $query = Alert::with(['machine.line.plant', 'alertRule', 'acknowledgedByUser'])
            ->orderByDesc('triggered_at');

        if ($request->input('active_only', false)) {
            $query->active();
        }

        if ($request->input('machine_id')) {
            $query->where('machine_id', $request->input('machine_id'));
        }

        $alerts = $query->limit(100)->get();

        return response()->json(['alerts' => $alerts]);
    }

    /**
     * POST /api/v1/andon/alerts/{alert}/acknowledge
     */
    public function acknowledgeAlert(Request $request, Alert $alert)
    {
        $alert->acknowledge($request->user()->id);

        return response()->json(['success' => true, 'alert' => $alert->fresh()]);
    }

    /**
     * POST /api/v1/andon/alerts/{alert}/resolve
     */
    public function resolveAlert(Request $request, Alert $alert)
    {
        $alert->resolve();

        return response()->json(['success' => true, 'alert' => $alert->fresh()]);
    }

    // ==================== RULES ====================

    /**
     * GET /api/v1/andon/rules
     */
    public function rules()
    {
        $rules = AlertRule::withCount(['alerts' => fn($q) => $q->active()])
            ->orderBy('name')
            ->get();

        return response()->json(['rules' => $rules]);
    }

    /**
     * POST /api/v1/andon/rules
     */
    public function storeRule(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:oee_below_target,machine_stopped,excessive_downtime,quality_drop,performance_drop',
            'severity' => 'required|string|in:critical,warning,info',
            'threshold' => 'required|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'scope_type' => 'nullable|string|in:plant,line,machine',
            'scope_id' => 'nullable|integer',
            'is_active' => 'boolean',
            'notify_email' => 'boolean',
            'cooldown_minutes' => 'nullable|integer|min:1',
        ]);

        $rule = AlertRule::create($validated);

        return response()->json(['success' => true, 'rule' => $rule], 201);
    }

    /**
     * PUT /api/v1/andon/rules/{rule}
     */
    public function updateRule(Request $request, AlertRule $rule)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|in:oee_below_target,machine_stopped,excessive_downtime,quality_drop,performance_drop',
            'severity' => 'sometimes|string|in:critical,warning,info',
            'threshold' => 'sometimes|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'scope_type' => 'nullable|string|in:plant,line,machine',
            'scope_id' => 'nullable|integer',
            'is_active' => 'boolean',
            'notify_email' => 'boolean',
            'cooldown_minutes' => 'nullable|integer|min:1',
        ]);

        $rule->update($validated);

        return response()->json(['success' => true, 'rule' => $rule->fresh()]);
    }

    /**
     * DELETE /api/v1/andon/rules/{rule}
     */
    public function destroyRule(AlertRule $rule)
    {
        $rule->delete();

        return response()->json(['success' => true]);
    }
}
