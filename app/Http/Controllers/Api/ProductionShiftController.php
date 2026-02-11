<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionShift;
use App\Models\Machine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductionShiftController extends Controller
{
    /**
     * Get active shift for a machine
     */
    public function show(int $machineId)
    {
        $activeShift = ProductionShift::where('machine_id', $machineId)
            ->where('status', 'active')
            ->with(['user.groups', 'shift', 'product'])
            ->first();
            
        if (!$activeShift) {
            return response()->json(['active_shift' => null]);
        }
        
        // Calculate Scheduled End Time
        $scheduledEndAt = null;
        if ($activeShift->shift && $activeShift->shift->start_time && $activeShift->shift->end_time) {
            $shiftStart = \Carbon\Carbon::parse($activeShift->shift->start_time);
            $shiftEnd = \Carbon\Carbon::parse($activeShift->shift->end_time);
            
            // Adjust for overnight shifts
            if ($shiftEnd->lt($shiftStart)) {
                $shiftEnd->addDay();
            }
            // Duration of the pattern
            $durationMinutes = $shiftStart->diffInMinutes($shiftEnd);
            
            // Apply duration to actual start time
            $scheduledEndAt = $activeShift->started_at->copy()->addMinutes($durationMinutes)->toISOString();
        }

        return response()->json([
            'active_shift' => [
                'id' => $activeShift->id,
                'machine_id' => $activeShift->machine_id,
                'product_id' => $activeShift->product_id,
                'started_at' => $activeShift->started_at->toISOString(),
                'scheduled_end_at' => $scheduledEndAt,
                'user_group' => $activeShift->user_group,
                'shift_name' => $activeShift->shift?->name,
                'product_name' => $activeShift->product?->name,
                'batch_number' => $activeShift->batch_number,
                'product' => $activeShift->product ? [ // Full product object for material loss conversion
                    'id' => $activeShift->product->id,
                    'name' => $activeShift->product->name,
                    'sku' => $activeShift->product->sku,
                    'unit_of_measure' => $activeShift->product->unit_of_measure,
                    'finished_unit' => $activeShift->product->finished_unit,
                    'fill_volume' => $activeShift->product->fill_volume,
                    'fill_volume_unit' => $activeShift->product->fill_volume_unit,
                ] : null,
                'started_by' => [
                    'id' => $activeShift->user->id,
                    'name' => $activeShift->user->name,
                    'email' => $activeShift->user->email,
                    'groups' => $activeShift->user->groups->pluck('name')->toArray(),
                ],
            ],
        ]);
    }
    
    /**
     * Start a new shift for a machine
     */
    public function start(Request $request, int $machineId)
    {
        $user = Auth::user();
        
        // Permission Check
        $machine = \App\Models\Machine::with('line')->findOrFail($machineId);
        if (!$user->canManagePlant($machine->line->plant_id)) {
            return response()->json(['error' => 'You exist, but you do not belong here. Access to this plant is restricted.'], 403);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'shift_id' => 'required|exists:shifts,id',
            'operator_user_id' => 'nullable|exists:users,id', // Optional: operator starting the shift
            'batch_number' => 'nullable|string|max:255',
        ]);

        // Check if there's already an active shift
        $existingShift = ProductionShift::where('machine_id', $machineId)
            ->where('status', 'active')
            ->first();
            
        if ($existingShift) {
            return response()->json([
                'error' => 'There is already an active shift for this machine.',
                'active_shift' => $existingShift,
            ], 400);
        }
        
        // Determine which user is the operator
        // If operator_user_id is provided, use that user; otherwise use logged-in user
        $operatorUserId = $request->input('operator_user_id') ?? $user->id;
        $operatorUser = \App\Models\User::with('groups')->find($operatorUserId);
        
        if (!$operatorUser) {
            return response()->json(['error' => 'Operator user not found.'], 404);
        }
        
        // Get operator's primary group (first one)
        $userGroups = $operatorUser->groups()->pluck('name')->toArray();
        $primaryGroup = $userGroups[0] ?? null;
        
        // Create new production shift
        $productionShift = ProductionShift::create([
            'machine_id' => $machineId,
            'user_id' => $operatorUserId, // The operator who's running the shift
            'product_id' => $request->input('product_id'),
            'shift_id' => $request->input('shift_id'),
            'user_group' => $primaryGroup,
            'started_at' => now(),
            'status' => 'active',
            'batch_number' => $request->input('batch_number'),
            'metadata' => [
                'all_user_groups' => $userGroups,
                'started_by_supervisor' => $operatorUserId !== $user->id ? $user->id : null, // Track if started by supervisor
            ],
        ]);
        
        $productionShift->load(['user.groups', 'shift', 'product']);
        
        return response()->json([
            'success' => true,
            'message' => 'Shift started successfully.',
            'active_shift' => [
                'id' => $productionShift->id,
                'machine_id' => $productionShift->machine_id,
                'started_at' => $productionShift->started_at->toISOString(),
                'user_group' => $productionShift->user_group,
                'shift_name' => $productionShift->shift?->name,
                'product_id' => $productionShift->product_id,
                'product_name' => $productionShift->product?->name,
                'product' => $productionShift->product,
                'batch_number' => $productionShift->batch_number,
                'started_by' => [
                    'id' => $operatorUser->id,
                    'name' => $operatorUser->name,
                    'email' => $operatorUser->email,
                    'groups' => $userGroups,
                ],
            ],
        ]);
    }

    /**
     * Store a manually created past shift report
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Permission Check (similar to update/end override)
        $userGroups = $user->groups()->pluck('name')->toArray();
        $allowedGroups = ['Admin', 'Supervisor', 'Manager'];
        $hasPermission = !empty(array_intersect($allowedGroups, $userGroups));
        
        if (!$hasPermission) {
            return response()->json([
                'error' => 'Only supervisors and administrators can manually create shift reports.',
            ], 403);
        }
        
        $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'product_id' => 'required|exists:products,id',
            'shift_id' => 'required|exists:shifts,id',
            'user_id' => 'required|exists:users,id', // Operator
            'started_at' => 'required|date',
            'ended_at' => 'required|date|after:started_at',
            'good_count' => 'nullable|integer|min:0',
            'reject_count' => 'nullable|integer|min:0',
            'downtime_minutes' => 'nullable|integer|min:0',
            'material_loss_units' => 'nullable|numeric|min:0',
            'batch_number' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            'changeovers' => 'nullable|array',
            'changeovers.*.to_product_id' => 'required|exists:products,id',
            'changeovers.*.changed_at' => 'required|date',
            'changeovers.*.batch_number' => 'nullable|string|max:255',
            'changeovers.*.notes' => 'nullable|string|max:500',
            'changeovers.*.good_count' => 'nullable|integer|min:0',
            'changeovers.*.reject_count' => 'nullable|integer|min:0',
            'downtime_records' => 'nullable|array',
            'downtime_records.*.reason_code_id' => 'required|exists:reason_codes,id',
            'downtime_records.*.minutes' => 'required|integer|min:0',
            'downtime_records.*.start_time' => 'nullable|date',
            'downtime_records.*.end_time' => 'nullable|date',
            'downtime_records.*.comment' => 'nullable|string',
        ]);
        
        // Get Operator info
        $operator = \App\Models\User::with('groups')->find($request->user_id);
        $operatorGroup = $operator->groups()->first()?->name;

        // Calculate Totals and Product Counts
        $initialGood = $request->integer('good_count', 0);
        $initialReject = $request->integer('reject_count', 0);
        
        $totalGood = $initialGood;
        $totalReject = $initialReject;
        
        $productCounts = [];
        // Add initial product
        $productCounts[] = [
            'product_id' => $request->product_id,
            'good_count' => $initialGood,
            'reject_count' => $initialReject,
            // We don't have precise start/end for segments here easily without complex logic involving changed_at
        ];

        $changeovers = $request->input('changeovers', []);
        if (is_array($changeovers)) {
            foreach ($changeovers as $co) {
                $coGood = (int)($co['good_count'] ?? 0);
                $coReject = (int)($co['reject_count'] ?? 0);
                $totalGood += $coGood;
                $totalReject += $coReject;
                
                $productCounts[] = [
                    'product_id' => $co['to_product_id'],
                    'good_count' => $coGood,
                    'reject_count' => $coReject
                ];
            }
        }

        // Calculate Downtime
        $downtimeRecords = $request->input('downtime_records', []);
        $totalDowntime = 0;
        if (count($downtimeRecords) > 0) {
            foreach ($downtimeRecords as $dt) {
                $totalDowntime += (int)$dt['minutes'];
            }
        } else {
            $totalDowntime = $request->integer('downtime_minutes', 0);
        }

        // Calculate Target Output based on product runs (weighted by duration)
        $startTime = \Carbon\Carbon::parse($request->started_at);
        $endTime = \Carbon\Carbon::parse($request->ended_at);
        
        $sortedChangeovers = collect($request->input('changeovers', []))->sortBy(function($co) {
            return \Carbon\Carbon::parse($co['changed_at'])->timestamp;
        })->values();

        // Prepare Downtime Events with calculating start/end times
        $simulatedDowntimeEvents = [];
        $currentDtCalculatedStart = $startTime->copy();
        foreach ($request->input('downtime_records', []) as $dt) {
            if (!empty($dt['start_time']) && !empty($dt['end_time'])) {
                $dtStart = \Carbon\Carbon::parse($dt['start_time']);
                $dtEnd = \Carbon\Carbon::parse($dt['end_time']);
            } else {
                $mins = (int)$dt['minutes'];
                if ($mins <= 0) continue;
                $dtStart = $currentDtCalculatedStart->copy();
                $dtEnd = $currentDtCalculatedStart->copy()->addMinutes($mins);
                $currentDtCalculatedStart = $dtEnd; // Advance for next generic downtime
            }
            $simulatedDowntimeEvents[] = [
                'start' => $dtStart,
                'end' => $dtEnd,
            ];
        }

        // Build Segments
        $segments = [];
        $currentStart = $startTime;
        $currentProductId = $request->product_id;
        
        foreach($sortedChangeovers as $co) {
            $coTime = \Carbon\Carbon::parse($co['changed_at']);
            // Bounds check
            if ($coTime->lt($startTime)) $coTime = $startTime; 
            if ($coTime->gt($endTime)) $coTime = $endTime;
            
            $segments[] = [
                'product_id' => $currentProductId,
                'start' => $currentStart,
                'end' => $coTime,
            ];
            
            $currentStart = $coTime;
            $currentProductId = $co['to_product_id'];
        }
        
        // Final segment
        $segments[] = [
            'product_id' => $currentProductId,
            'start' => $currentStart,
            'end' => $endTime,
        ];
        
        $totalTarget = 0;
        $productTargets = [];

        foreach ($segments as $segment) {
             $segStart = $segment['start'];
             $segEnd = $segment['end'];
             
             // Segment Duration (hours)
             $segDurationHours = $segStart->diffInHours($segEnd);
             if ($segDurationHours <= 0) continue;
             
             // Calculate Overlapping Downtime
             $segDowntimeHours = 0;
             foreach ($simulatedDowntimeEvents as $dt) {
                 $overlapStart = $dt['start']->max($segStart);
                 $overlapEnd = $dt['end']->min($segEnd);
                 
                 if ($overlapEnd->gt($overlapStart)) {
                     $segDowntimeHours += $overlapStart->diffInHours($overlapEnd);
                 }
             }
             
             $netRuntimeHours = max(0, $segDurationHours - $segDowntimeHours);
             
             // Get Ideal Rate
             $config = \App\Models\MachineProductConfig::where('machine_id', $request->machine_id)
                    ->where('product_id', $segment['product_id'])
                    ->first();
             $idealRate = $config ? $config->ideal_rate : (\App\Models\Machine::find($request->machine_id)->default_ideal_rate ?? 0);
             
             $segTarget = floor($netRuntimeHours * $idealRate);
             $totalTarget += $segTarget;
             
             if (!isset($productTargets[$segment['product_id']])) {
                 $productTargets[$segment['product_id']] = 0;
             }
             $productTargets[$segment['product_id']] += $segTarget;
             $productIdealRates[$segment['product_id']] = $idealRate;
        }

        $targetOutput = floor($totalTarget);
        
        // Update productCounts with calculated targets and ideal rates
        foreach ($productCounts as &$pc) {
            if (isset($pc['product_id'])) {
                 if (isset($productTargets[$pc['product_id']])) {
                    $pc['target_output'] = $productTargets[$pc['product_id']];
                 }
                 if (isset($productIdealRates[$pc['product_id']])) {
                    $pc['ideal_rate'] = $productIdealRates[$pc['product_id']];
                 }
            }
        }
        
        // Use average ideal rate or initial for display?
        // Let's use the initial product's rate for the simple 'ideal_rate' field to avoid confusion, 
        // even though target is calculated more precisely.
        $initialConfig = \App\Models\MachineProductConfig::where('machine_id', $request->machine_id)
                ->where('product_id', $request->product_id)
                ->first();
        $displayIdealRate = $initialConfig ? $initialConfig->ideal_rate : (\App\Models\Machine::find($request->machine_id)->default_ideal_rate ?? 0);

        // Create Metadata
        $metadata = [
            'good_count' => $totalGood,
            'reject_count' => $totalReject,
            'total_output' => $totalGood + $totalReject,
            'downtime_minutes' => $totalDowntime,
            'target_output' => $targetOutput,
            'ideal_rate' => $displayIdealRate,
            'material_loss_units' => $request->float('material_loss_units', 0),
            'quality_score' => $this->calculateQualityScore($totalGood, $totalReject, $request->float('material_loss_units', 0)),
            'manual_entry' => true,
            'product_counts' => $productCounts,
            'created_by' => $user->id,
            'created_at' => now()->toIsoString(),
        ];
        
        // Create Shift
        $shift = ProductionShift::create([
            'machine_id' => $request->machine_id,
            'user_id' => $request->user_id,
            'product_id' => $request->product_id,
            'shift_id' => $request->shift_id,
            'user_group' => $operatorGroup,
            'started_at' => \Carbon\Carbon::parse($request->started_at),
            'ended_at' => \Carbon\Carbon::parse($request->ended_at),
            'status' => 'completed',
            'batch_number' => $request->batch_number,
            'metadata' => $metadata,
            'comment' => $request->comment,
        ]);
        
        // Process changeovers if provided
        if (!empty($changeovers)) {
            $fromProductId = $shift->product_id; // Start with initial product
            
            foreach ($changeovers as $changeoverData) {
                $changeover = \App\Models\ProductChangeover::create([
                    'production_shift_id' => $shift->id,
                    'from_product_id' => $fromProductId,
                    'to_product_id' => $changeoverData['to_product_id'],
                    'changed_at' => \Carbon\Carbon::parse($changeoverData['changed_at']),
                    'recorded_by' => $user->id,
                    'notes' => $changeoverData['notes'] ?? null,
                ]);
                
                // Update batch number if provided in changeover
                if (!empty($changeoverData['batch_number'])) {
                    $shift->batch_number = $changeoverData['batch_number'];
                }
                
                // Next changeover's from_product is this changeover's to_product
                $fromProductId = $changeoverData['to_product_id'];
            }
            
            // Save final batch number if it was updated
            if ($shift->isDirty('batch_number')) {
                $shift->save();
            }
        }
        
        // Process Downtime Records
        if (!empty($downtimeRecords)) {
            $currentDtStart = $shift->started_at->copy();
            foreach ($downtimeRecords as $dt) {
                // Determine timestamps and duration
                if (!empty($dt['start_time']) && !empty($dt['end_time'])) {
                    $dtStart = \Carbon\Carbon::parse($dt['start_time']);
                    $dtEnd = \Carbon\Carbon::parse($dt['end_time']);
                    $durationMin = $dtStart->diffInMinutes($dtEnd);
                } else {
                    $durationMin = (int)$dt['minutes'];
                    if ($durationMin <= 0) continue;
                    $dtStart = $currentDtStart->copy();
                    $dtEnd = $currentDtStart->copy()->addMinutes($durationMin);
                }
                
                if ($durationMin <= 0) continue;
                
                \App\Models\DowntimeEvent::create([
                    'production_shift_id' => $shift->id,
                    'machine_id' => $shift->machine_id,
                    'reason_code_id' => $dt['reason_code_id'],
                    'start_time' => $dtStart,
                    'end_time' => $dtEnd,
                    'duration_seconds' => $durationMin * 60,
                    'comment' => $dt['comment'] ?? null,
                ]);
                
                $currentDtStart = $dtEnd;
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Shift report created successfully.',
            'shift' => $shift
        ]);
    }
    
    /**
     * End an active shift for a machine
     */
    public function end(Request $request, int $machineId)
    {
        // Permission Check
        $user = Auth::user();
        $machine = \App\Models\Machine::with('line')->findOrFail($machineId);
        if (!$user->canManagePlant($machine->line->plant_id)) {
            return response()->json(['error' => 'Unauthorized for this plant.'], 403);
        }

        $request->validate([
            'good_count' => 'nullable|integer|min:0',
            'reject_count' => 'nullable|integer|min:0',
            'comment' => 'nullable|string|max:500',
            'early_exit_reason_id' => 'nullable|integer|exists:reason_codes,id',
        ]);

        $activeShift = ProductionShift::where('machine_id', $machineId)
            ->where('status', 'active')
            ->with('user') // Load the user who started the shift
            ->first();
            
        if (!$activeShift) {
            return response()->json([
                'error' => 'No active shift found for this machine.',
            ], 404);
        }
        
        // --- OWNERSHIP VALIDATION ---
        // Check if current user is the one who started the shift
        $isOwner = $activeShift->user_id === $user->id;
        
        // Check if user has override permission (Admin, Supervisor, Manager)
        $userGroups = $user->groups()->pluck('name')->toArray();
        $overrideGroups = ['Admin', 'Supervisor', 'Manager'];
        $hasOverridePermission = !empty(array_intersect($overrideGroups, $userGroups));
        
        // User must be either the owner OR have override permission
        if (!$isOwner && !$hasOverridePermission) {
            return response()->json([
                'error' => 'You can only end shifts that you started.',
                'details' => [
                    'shift_started_by' => $activeShift->user->name,
                    'shift_started_by_id' => $activeShift->user_id,
                    'current_user' => $user->name,
                    'current_user_id' => $user->id,
                    'message' => 'Only the user who started this shift or a supervisor/admin can end it.',
                ],
            ], 403);
        }
        
        // Log if this was an override  (supervisor ending someone else's shift)
        if (!$isOwner && $hasOverridePermission) {
            \Log::info('Shift ended by supervisor override', [
                'shift_id' => $activeShift->id,
                'shift_owner_id' => $activeShift->user_id,
                'shift_owner_name' => $activeShift->user->name,
                'ended_by_id' => $user->id,
                'ended_by_name' => $user->name,
                'override_groups' => array_intersect($overrideGroups, $userGroups),
            ]);
        }
        
        $endTime = now();
        
        // Calculate Scheduled End Time for Early Exit Logic
        $scheduledEndAt = null;
        if ($activeShift->shift && $activeShift->shift->start_time && $activeShift->shift->end_time) {
            $shiftStart = \Carbon\Carbon::parse($activeShift->shift->start_time);
            $shiftEnd = \Carbon\Carbon::parse($activeShift->shift->end_time);
            if ($shiftEnd->lt($shiftStart)) {
                $shiftEnd->addDay();
            }
            // Logic: Apply shift pattern duration to actual start time
            $durationMinutes = $shiftStart->diffInMinutes($shiftEnd);
            $scheduledEndAt = $activeShift->started_at->copy()->addMinutes($durationMinutes);
        }

        // Processing Early Exit Logic
        if ($request->input('early_exit_reason_id') && $scheduledEndAt && $endTime->lt($scheduledEndAt)) {
            // Only process if gap is significant (> 1 min) to avoid micro-downtimes
            if ($endTime->diffInMinutes($scheduledEndAt) > 1) {
                 // 1. Create Downtime to fill the gap
                 \App\Models\DowntimeEvent::create([
                    'machine_id' => $machineId,
                    'shift_id' => $activeShift->id,
                    'reason_code_id' => $request->input('early_exit_reason_id'),
                    'start_time' => $endTime, // Start now
                    'end_time' => $scheduledEndAt, // End at scheduled time
                    'duration_seconds' => $scheduledEndAt->diffInSeconds($endTime),
                    'comment' => 'Early Shift Exit: ' . ($request->input('comment') ?? 'Auto-generated'),
                    'is_active' => false, // Completed event
                 ]);
                 
                 // 2. Extend Shift End Time to scheduled end
                 $endTime = $scheduledEndAt;
            }
        }

        // Update shift
        // Calculate downtime duration for this shift
        $downtimeSeconds = \App\Models\DowntimeEvent::where('machine_id', $machineId)
            ->where('start_time', '>=', $activeShift->started_at)
            ->where('start_time', '<=', $endTime)
            ->sum('duration_seconds');
            
        $downtimeMinutes = round($downtimeSeconds / 60);

        // Calculate Material Loss (OEE-affecting only)
        $materialLossUnits = \App\Models\MaterialLoss::where('shift_id', $activeShift->id)
            ->whereHas('category', fn($q) => $q->where('affects_oee', true))
            ->sum('quantity');
            
        $materialLossCost = \App\Models\MaterialLoss::where('shift_id', $activeShift->id)
            ->sum('cost_estimate');

        // Calculate Target
        $shiftDurationMinutes = $activeShift->started_at->diffInMinutes($endTime);
        // Find Ideal Rate for the shift's product
        $idealRate = 0;
        if ($activeShift->product_id) {
            $config = \App\Models\MachineProductConfig::where('machine_id', $machineId)
                ->where('product_id', $activeShift->product_id)
                ->first();
            if ($config) {
                $idealRate = $config->ideal_rate; // units per hour
            } else {
                 // Fallback to machine default or find product in history
                 $machine = \App\Models\Machine::find($machineId);
                 $idealRate = $machine->default_ideal_rate ?? 0;
            }
        }

        // Calculate Target based on Shift Pattern Duration
        // Target should represent what SHOULD be produced during the scheduled shift,
        // not based on actual elapsed time. This aligns with OEE principles.
        
        // Try to get duration from shift pattern first
        $shiftPattern = \App\Models\Shift::find($activeShift->shift_id);
        
        if ($shiftPattern && $shiftPattern->start_time && $shiftPattern->end_time) {
            // Calculate scheduled shift duration from pattern
            $patternStartTime = \Carbon\Carbon::parse($shiftPattern->start_time);
            $patternEndTime = \Carbon\Carbon::parse($shiftPattern->end_time);
            
            // Handle overnight shifts (end_time < start_time)
            if ($patternEndTime->lt($patternStartTime)) {
                $patternEndTime->addDay();
            }
            
            $shiftDurationMinutes = $patternStartTime->diffInMinutes($patternEndTime);
        } else {
            // Fallback: use actual elapsed time if no shift pattern
            $shiftDurationMinutes = $activeShift->started_at->diffInMinutes($endTime);
        }

        // --- Multi-Product Target Calculation ---
        // 1. Get all changeovers
        $changeovers = $activeShift->productChangeovers()->orderBy('changed_at')->get();
        // 2. Get all downtime events
        $downtimeEvents = \App\Models\DowntimeEvent::where('shift_id', $activeShift->id)
            ->where('machine_id', $machineId)
            ->get();

        $segments = [];
        $currentStart = $activeShift->started_at;
        // Start with the shift's initial product
        $currentProductId = $activeShift->product_id; 

        // Build segments
        foreach ($changeovers as $changeover) {
             // Validate if changeover is within shift bounds (sanity check)
             if ($changeover->changed_at->lt($activeShift->started_at) || $changeover->changed_at->gt($endTime)) {
                 continue; 
             }

             $segments[] = [
                 'product_id' => $currentProductId,
                 'start' => $currentStart,
                 'end' => $changeover->changed_at,
             ];
             $currentStart = $changeover->changed_at;
             $currentProductId = $changeover->to_product_id;
        }
        // Add final segment
        $segments[] = [
            'product_id' => $currentProductId,
            'start' => $currentStart,
            'end' => $endTime, // Or shift scheduled end? Using actual end for now to match runtime
        ];

        $targetOutput = 0;
        $productTargets = []; // To store individual targets

        foreach ($segments as $segment) {
            if (!$segment['product_id']) continue;

            $segStart = $segment['start'];
            $segEnd = $segment['end'];
            
            // Calculate segment duration (hours)
            $segmentDurationHours = $segStart->diffInHours($segEnd); // Allows float
            if ($segmentDurationHours <= 0) continue;

            // Calculate downtime overlapping this segment
            $segmentDowntimeHours = 0;
            foreach ($downtimeEvents as $dt) {
                 if (!$dt->start_time || !$dt->end_time) continue;

                 // Calculate intersection
                 $overlapStart = $dt->start_time->max($segStart);
                 $overlapEnd = $dt->end_time->min($segEnd);

                 if ($overlapEnd->gt($overlapStart)) {
                     $segmentDowntimeHours += $overlapStart->diffInHours($overlapEnd);
                 }
            }

            $netRuntimeHours = max(0, $segmentDurationHours - $segmentDowntimeHours);

            // Get Ideal Rate for this product
            $pIdealRate = 0;
            $pConfig = \App\Models\MachineProductConfig::where('machine_id', $machineId)
                ->where('product_id', $segment['product_id'])
                ->first();
            
            if ($pConfig) {
                $pIdealRate = $pConfig->ideal_rate;
            } else {
                 $machine = \App\Models\Machine::find($machineId);
                 $pIdealRate = $machine->default_ideal_rate ?? 0;
            }

            $segmentTarget = floor($netRuntimeHours * $pIdealRate);
            $targetOutput += $segmentTarget;

            // Aggregate per product for metadata
            if (!isset($productTargets[$segment['product_id']])) {
                $productTargets[$segment['product_id']] = 0;
            }
            $productTargets[$segment['product_id']] += $segmentTarget;
            $productIdealRates[$segment['product_id']] = $pIdealRate;
        }

        // Handle per-product counts if provided (for shifts with changeovers)
        $productCounts = $request->input('product_counts', []);
        $totalGoodCount = 0;
        $totalRejectCount = 0;
        
        if (!empty($productCounts)) {
            // Calculate totals from per-product counts and inject target info
            foreach ($productCounts as &$pc) {
                $totalGoodCount += (int) ($pc['good_count'] ?? 0);
                $totalRejectCount += (int) ($pc['reject_count'] ?? 0);
                
                // Add calculated target to the product count entry if product_id matches
                if (isset($pc['product_id'])) {
                    if (isset($productTargets[$pc['product_id']])) {
                        $pc['target_output'] = $productTargets[$pc['product_id']];
                    }
                    if (isset($productIdealRates[$pc['product_id']])) {
                        $pc['ideal_rate'] = $productIdealRates[$pc['product_id']];
                    }
                }
            }
        } else {
            // Use single values (no changeovers)
            $totalGoodCount = $request->input('good_count', 0);
            $totalRejectCount = $request->input('reject_count', 0);
        }

        // Update shift with stats in metadata
        $activeShift->update([
            'ended_at' => $endTime,
            'status' => 'completed',
            'metadata' => array_merge($activeShift->metadata ?? [], [
                'end_notes' => $request->comment,
                'good_count' => $totalGoodCount,
                'reject_count' => $totalRejectCount,
                'total_output' => $totalGoodCount + $totalRejectCount,
                'product_counts' => !empty($productCounts) ? $productCounts : null, // Store per-product breakdown
                'downtime_minutes' => $downtimeMinutes,
                'target_output' => $targetOutput,
                'ideal_rate' => $idealRate,
                'material_loss_units' => $materialLossUnits,
                'material_loss_cost' => $materialLossCost,
                'quality_score' => $this->calculateQualityScore(
                    $totalGoodCount,
                    $totalRejectCount,
                    $materialLossUnits
                ),
                'ended_by_user_id' => $user->id,
                'ended_by_user_name' => $user->name,
                'ended_by_override' => !$isOwner && $hasOverridePermission, // Track if supervisor override
            ])
        ]);

        // If production data provided, create a Production Log entry to record it
        // This makes it visible in OEE charts immediately as a summary for the shift
        // Create Production Logs
        // If we have detailed product counts (changeovers), create separate logs for accurate product reporting
        if (!empty($productCounts)) {
            foreach ($productCounts as $pc) {
                $pGood = (int)($pc['good_count'] ?? 0);
                $pReject = (int)($pc['reject_count'] ?? 0);
                
                // Ensure we have a product ID
                $pId = $pc['product_id'] ?? $activeShift->product_id;
                
                // Timestamps from frontend (based on getProductRuns) or fallback to shift bounds
                $pStart = isset($pc['start_time']) ? \Carbon\Carbon::parse($pc['start_time']) : $activeShift->started_at;
                $pEnd = isset($pc['end_time']) ? \Carbon\Carbon::parse($pc['end_time']) : $endTime;

                \App\Models\ProductionLog::create([
                    'machine_id' => $machineId,
                    'product_id' => $pId,
                    'shift_id' => $activeShift->shift_id,
                    'start_time' => $pStart,
                    'end_time' => $pEnd,
                    'good_count' => $pGood,
                    'reject_count' => $pReject,
                ]);
            }
        } 
        // Fallback for single product production (no changeovers recorded correctly)
        elseif ($request->has('good_count') || $request->has('reject_count')) {
            $goodCount = $request->input('good_count', 0);
            $rejectCount = $request->input('reject_count', 0);
            
            // Try to identify product. Use the Shift's product.
            $productId = $activeShift->product_id;

            // Create log spanning the shift
            \App\Models\ProductionLog::create([
                'machine_id' => $machineId,
                'product_id' => $productId,
                'shift_id' => $activeShift->shift_id, 
                'start_time' => $activeShift->started_at,
                'end_time' => $endTime,
                'good_count' => $goodCount,
                'reject_count' => $rejectCount,
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Shift ended successfully.',
            'shift' => [
                'id' => $activeShift->id,
                'started_at' => $activeShift->started_at->toISOString(),
                'ended_at' => $activeShift->ended_at->toISOString(),
                'duration_minutes' => $activeShift->started_at->diffInMinutes($activeShift->ended_at),
            ],
        ]);
    }
    
    /**
     * Get all active shifts (for dashboard overview)
     */
    public function activeShifts()
    {
        $query = ProductionShift::where('status', 'active')
            ->with(['user.groups', 'machine', 'shift']);

        // Global Plant Scope
        if (!auth()->user()->isAdmin()) {
             $allowedPlants = auth()->user()->plants()->pluck('id');
             $query->whereHas('machine.line', function($q) use ($allowedPlants) {
                 $q->whereIn('plant_id', $allowedPlants);
             });
        }

        $activeShifts = $query->get()
            ->map(function ($shift) {
                return [
                    'id' => $shift->id,
                    'machine_id' => $shift->machine_id,
                    'machine_name' => $shift->machine->name,
                    'started_at' => $shift->started_at->toISOString(),
                    'user_group' => $shift->user_group,
                    'batch_number' => $shift->batch_number,
                    'started_by' => [
                        'id' => $shift->user->id,
                        'name' => $shift->user->name,
                        'groups' => $shift->user->groups->pluck('name')->toArray(),
                    ],
                ];
            });
            
        return response()->json(['active_shifts' => $activeShifts]);
    }
    
    /**
     * Get shift history for a machine (completed and active shifts)
     */
    public function history(int $machineId)
    {
        $shifts = ProductionShift::where('machine_id', $machineId)
            ->with(['user', 'shift', 'product', 'downtimeEvents.reasonCode', 'editor', 'productChangeovers.fromProduct', 'productChangeovers.toProduct', 'productChangeovers.recordedBy'])
            ->orderBy('started_at', 'desc')
            ->limit(50) // Last 50 shifts
            ->get()
            ->map(function ($shift) {
                $meta = $shift->metadata ?? [];
                
                // --- Downtime Calculation ---
                // 1. Try strict relationship
                $dtEvents = $shift->downtimeEvents;
                
                // 2. Fallback: If no linked events, try Time-Based match (for older shifts or if link missed)
                if ($dtEvents->isEmpty() && $shift->ended_at) {
                     $dtEvents = \App\Models\DowntimeEvent::with('reasonCode')
                        ->where('machine_id', $shift->machine_id)
                        ->whereBetween('start_time', [$shift->started_at, $shift->ended_at])
                        ->get();
                }
                
                // 3. Check for manual override in metadata
                if (isset($meta['downtime_minutes'])) {
                    $downtimeMin = (int)$meta['downtime_minutes'];
                    $downtimeSeconds = $downtimeMin * 60; // Approximate
                } else {
                    $downtimeSeconds = $dtEvents->sum('duration_seconds');
                    $downtimeMin = round($downtimeSeconds / 60);
                }
                
                // --- Reasons String ---
                $downtimeReason = null;
                if ($dtEvents->isNotEmpty()) {
                     $summary = $dtEvents->groupBy(fn($e) => $e->reasonCode->description ?? 'Unknown')
                        ->map(fn($group) => $group->sum('duration_seconds'))
                        ->sortDesc();
                     
                     $topReason = $summary->keys()->first();
                     $topDuration = round($summary->first() / 60);
                     $downtimeReason = "{$topReason} ({$topDuration}m)";
                     
                     if ($summary->count() > 1) {
                         $downtimeReason .= " + " . ($summary->count() - 1) . " others";
                     }
                }

                // --- Target / OEE ---
                // Use stored values from metadata (calculated when shift was ended using shift pattern duration)
                $targetOutput = $meta['target_output'] ?? 0;
                $idealRate = $meta['ideal_rate'] ?? 0;
                
                // If target wasn't stored, try to calculate it using shift pattern duration (legacy data)
                if ($targetOutput == 0 && $shift->product_id) {
                    $config = \App\Models\MachineProductConfig::where('machine_id', $shift->machine_id)
                        ->where('product_id', $shift->product_id)
                        ->first();
                    
                    if ($config && $config->ideal_rate > 0) {
                        $idealRate = $config->ideal_rate;
                        
                        // Use shift pattern duration, not actual elapsed
                        if ($shift->shift && $shift->shift->start_time && $shift->shift->end_time) {
                            $patternStart = \Carbon\Carbon::parse($shift->shift->start_time);
                            $patternEnd = \Carbon\Carbon::parse($shift->shift->end_time);
                            if ($patternEnd->lt($patternStart)) $patternEnd->addDay();
                            $patternMinutes = $patternStart->diffInMinutes($patternEnd);
                            $targetOutput = floor(($patternMinutes / 60) * $idealRate);
                        }
                    }
                }

                // Calculate Standard Duration
                $standardDuration = 0;
                if ($shift->shift) {
                    $s = \Carbon\Carbon::parse($shift->shift->start_time);
                    $e = \Carbon\Carbon::parse($shift->shift->end_time);
                    if ($e->lt($s)) $e->addDay();
                    $standardDuration = $s->diffInMinutes($e);
                }

                return [
                    'id' => $shift->id,
                    'machine_id' => $shift->machine_id,
                    'user_id' => $shift->user_id,
                    'user_name' => $shift->user->name,
                    'user_group' => $shift->user_group,
                    'shift_name' => $shift->shift?->name,
                    'shift_type' => $shift->shift?->type,
                    'product_id' => $shift->product_id,
                    'product_name' => $shift->product?->name ?? 'N/A',
                    'good_count' => $shift->good_count ?? $meta['good_count'] ?? 0,
                    'reject_count' => $shift->reject_count ?? $meta['reject_count'] ?? 0,
                    'total_output' => ($shift->good_count ?? $meta['good_count'] ?? 0) + ($shift->reject_count ?? $meta['reject_count'] ?? 0),
                    'target_output' => $targetOutput,
                    'ideal_rate' => $idealRate,
                    'product_counts' => $meta['product_counts'] ?? [],
                    'downtime_minutes' => $downtimeMin,
                    'downtime_reason' => $downtimeReason,
                    'material_loss_units' => $meta['material_loss_units'] ?? 0,
                    'material_loss_cost' => $meta['material_loss_cost'] ?? 0,
                    'quality_score' => $meta['quality_score'] ?? null,
                    'standard_duration_minutes' => $standardDuration,
                    'started_at' => $shift->started_at->toISOString(),
                    'ended_at' => $shift->ended_at?->toISOString(),
                    'status' => $shift->status,
                    'edited_by' => $shift->editor ? [
                        'id' => $shift->editor->id,
                        'name' => $shift->editor->name,
                    ] : null,
                    'edited_at' => $shift->edited_at?->toISOString(),
                    'batch_number' => $shift->batch_number,
                    'changeovers' => $shift->productChangeovers->map(function($co) {
                        return [
                            'id' => $co->id,
                            'from_product' => ['id' => $co->from_product_id, 'name' => $co->fromProduct->name],
                            'to_product' => ['id' => $co->to_product_id, 'name' => $co->toProduct->name],
                            'changed_at' => $co->changed_at->toISOString(),
                            'recorded_by' => $co->recordedBy->name,
                        ];
                    })->toArray(),
                ];
            });
            
        return response()->json(['shifts' => $shifts]);
    }

    /**
     * Update/Edit a completed shift report
     * Only accessible to users in specific groups (Admin, Supervisor)
     */
    public function update(Request $request, int $shiftId)
    {
        $user = Auth::user();
        
        // Find the shift
        $shift = ProductionShift::with(['machine.line', 'productChangeovers', 'downtimeEvents'])->findOrFail($shiftId);
        
        // Permission Check
        if (!$user->canManagePlant($shift->machine->line->plant_id)) {
            return response()->json(['error' => 'Unauthorized for this plant.'], 403);
        }
        
        // Additional Permission: Only allow specific groups to edit
        $userGroups = $user->groups()->pluck('name')->toArray();
        $allowedGroups = ['Admin', 'Supervisor', 'Manager'];
        
        if (!array_intersect($allowedGroups, $userGroups)) {
            return response()->json([
                'error' => 'Only supervisors and administrators can edit shift reports.',
                'required_groups' => $allowedGroups
            ], 403);
        }
        
        // Validate input
        $request->validate([
            'shift_id' => 'nullable|exists:shifts,id',
            'product_id' => 'nullable|exists:products,id',
            'user_id' => 'nullable|exists:users,id',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date|after:started_at',
            'good_count' => 'nullable|integer|min:0',
            'reject_count' => 'nullable|integer|min:0',
            'material_loss_units' => 'nullable|numeric|min:0',
            'downtime_minutes' => 'nullable|integer|min:0',
            'batch_number' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
            // Detailed Changeovers
            'changeovers' => 'nullable|array',
            'changeovers.*.to_product_id' => 'required|exists:products,id',
            'changeovers.*.changed_at' => 'required|date',
            'changeovers.*.batch_number' => 'nullable|string|max:255',
            'changeovers.*.notes' => 'nullable|string|max:500',
            // Detailed Downtime
            'downtime_records' => 'nullable|array',
            'downtime_records.*.reason_code_id' => 'required|exists:reason_codes,id',
            'downtime_records.*.minutes' => 'nullable|integer|min:0',
            'downtime_records.*.start_time' => 'nullable|date',
            'downtime_records.*.end_time' => 'nullable|date',
            'downtime_records.*.comment' => 'nullable|string',
            // Per Product Counts (explicit)
            'product_counts' => 'nullable|array',
        ]);
        
        // 1. Update Core Shift Details
        if ($request->has('shift_id')) $shift->shift_id = $request->input('shift_id');
        if ($request->has('product_id')) $shift->product_id = $request->input('product_id'); // Initial Product
        if ($request->has('user_id')) $shift->user_id = $request->input('user_id');
        if ($request->has('started_at')) $shift->started_at = \Carbon\Carbon::parse($request->input('started_at'));
        if ($request->has('ended_at')) $shift->ended_at = \Carbon\Carbon::parse($request->input('ended_at'));
        if ($request->has('batch_number')) $shift->batch_number = $request->input('batch_number');
        
        $shift->save(); // Save basics first
        
        // 2. Sync Changeovers (Full Replace Strategy for simplicity in Edit Report)
        // Note: In a real-world high-concurrency app, we might diff, but here replace is safer for consistency.
        if ($request->has('changeovers')) {
            $shift->productChangeovers()->delete(); // Remove old
            
            $changeoversData = $request->input('changeovers', []);
            $fromProductId = $shift->product_id;
            
            // Sort by time
            usort($changeoversData, function($a, $b) {
                return strtotime($a['changed_at']) - strtotime($b['changed_at']);
            });

            foreach ($changeoversData as $coData) {
                \App\Models\ProductChangeover::create([
                    'production_shift_id' => $shift->id,
                    'from_product_id' => $fromProductId,
                    'to_product_id' => $coData['to_product_id'],
                    'changed_at' => \Carbon\Carbon::parse($coData['changed_at']),
                    'recorded_by' => $user->id, // Editor records this
                    'notes' => $coData['notes'] ?? null,
                    'batch_number' => $coData['batch_number'] ?? null,
                    'good_count' => $coData['good_count'] ?? 0,
                    'reject_count' => $coData['reject_count'] ?? 0,
                    'material_loss_units' => $coData['material_loss_units'] ?? 0,
                ]);
                $fromProductId = $coData['to_product_id'];
            }
        }
        
        // 3. Sync Downtime Events (Full Replace Strategy)
        if ($request->has('downtime_records')) {
            // Delete existing linked events
            $shift->downtimeEvents()->delete(); 
            // Also Consider: If we strictly link by ID in frontend, we could update. 
            // But 'Full Replace' ensures the frontend table is the source of truth.
            
            $downtimeData = $request->input('downtime_records', []);
            $simulatedDowntimeEvents = [];
            $currentDtStart = $shift->started_at->copy();

            foreach ($downtimeData as $dt) {
                // Calculate Times
                if (!empty($dt['start_time']) && !empty($dt['end_time'])) {
                    $dtStart = \Carbon\Carbon::parse($dt['start_time']);
                    $dtEnd = \Carbon\Carbon::parse($dt['end_time']);
                    $durationSeconds = $dtStart->diffInSeconds($dtEnd);
                } else {
                    $mins = (int)($dt['minutes'] ?? 0);
                    if ($mins <= 0) continue;
                    $dtStart = $currentDtStart->copy();
                    $dtEnd = $currentDtStart->copy()->addMinutes($mins);
                    $durationSeconds = $mins * 60;
                    $currentDtStart = $dtEnd; // Advance for next generic
                }
                
                \App\Models\DowntimeEvent::create([
                    'production_shift_id' => $shift->id,
                    'machine_id' => $shift->machine_id,
                    'reason_code_id' => $dt['reason_code_id'],
                    'start_time' => $dtStart,
                    'end_time' => $dtEnd,
                    'duration_seconds' => $durationSeconds,
                    'comment' => $dt['comment'] ?? null,
                ]);
                
                $simulatedDowntimeEvents[] = ['start' => $dtStart, 'end' => $dtEnd];
            }
        } else {
            // Reload for calculation if not replaced
            $simulatedDowntimeEvents = $shift->downtimeEvents->map(function($dt) {
                return ['start' => $dt->start_time, 'end' => $dt->end_time];
            })->toArray();
        }

        // 4. Calculate Totals (Initial + Changeovers)
        $initialRunData = $request->input('initial_run', []);
        $initialGood = (int)($initialRunData['good_count'] ?? $request->input('good_count', 0));
        $initialReject = (int)($initialRunData['reject_count'] ?? $request->input('reject_count', 0));
        $initialLoss = (float)($initialRunData['material_loss_units'] ?? $request->input('material_loss_units', 0));
        $initialBatch = $initialRunData['batch_number'] ?? $request->input('batch_number');

        // Sum Changeovers
        $changeoverGood = 0;
        $changeoverReject = 0;
        $changeoverLoss = 0;
        
        foreach ($request->input('changeovers', []) as $co) {
            $changeoverGood += (int)($co['good_count'] ?? 0);
            $changeoverReject += (int)($co['reject_count'] ?? 0);
            $changeoverLoss += (float)($co['material_loss_units'] ?? 0);
        }

        // Update Shift Totals
        $shift->good_count = $initialGood + $changeoverGood;
        $shift->reject_count = $initialReject + $changeoverReject;
        $shift->material_loss_units = $initialLoss + $changeoverLoss;
        $shift->save();

        // 5. Update Metadata with Detailed Initial Run Info
        $metadata = $shift->metadata ?? [];
        $metadata['initial_run'] = [
            'good_count' => $initialGood,
            'reject_count' => $initialReject,
            'material_loss_units' => $initialLoss,
            'batch_number' => $initialBatch
        ];
        
        // 6. Recalculate Stats & Targets based on new segments
        $shift->load('productChangeovers'); // Reload with new changeovers
        
        $segments = [];
        $currentStart = $shift->started_at;
        $currentProductId = $shift->product_id;
        
        foreach ($shift->productChangeovers as $co) {
            $segments[] = [
                'product_id' => $currentProductId,
                'start' => $currentStart,
                'end' => $co->changed_at,
            ];
            $currentStart = $co->changed_at;
            $currentProductId = $co->to_product_id;
        }
        $segments[] = [
            'product_id' => $currentProductId,
            'start' => $currentStart,
            'end' => $shift->ended_at,
        ];
        
        $totalTarget = 0;
        $productTargets = [];
        $productIdealRates = [];

        foreach ($segments as $segment) {
            $segStart = $segment['start'];
            $segEnd = $segment['end'];
            
            // Bounds check
            if ($segEnd->lt($segStart)) continue;

            $durationHours = $segStart->diffInHours($segEnd);
            
            // Calculate Overlap for Net Runtime
            $downtimeHours = 0;
            foreach ($simulatedDowntimeEvents as $dt) {
                // Ensure carbon objects
                $dtStart = \Carbon\Carbon::parse($dt['start']); 
                $dtEnd = \Carbon\Carbon::parse($dt['end']);
                
                $overlapStart = $dtStart->max($segStart);
                $overlapEnd = $dtEnd->min($segEnd);
                
                if ($overlapEnd->gt($overlapStart)) {
                    $downtimeHours += $overlapStart->diffInHours($overlapEnd);
                }
            }
            
            $netRuntime = max(0, $durationHours - $downtimeHours);
            
            // Get Ideal Rate
             $config = \App\Models\MachineProductConfig::where('machine_id', $shift->machine_id)
                    ->where('product_id', $segment['product_id'])
                    ->first();
             $idealRate = $config ? $config->ideal_rate : (\App\Models\Machine::find($shift->machine_id)->default_ideal_rate ?? 0);
             
             $segTarget = floor($netRuntime * $idealRate);
             $totalTarget += $segTarget;
             
             if (!isset($productTargets[$segment['product_id']])) $productTargets[$segment['product_id']] = 0;
             $productTargets[$segment['product_id']] += $segTarget;
             $productIdealRates[$segment['product_id']] = $idealRate;
        }
        
        // 5. Update Metadata
        $metadata = $shift->metadata ?? [];
        $oldMetadata = $metadata; // For audit
        
        // Counts
        // Use user provided product_counts to sum, or use good_count/reject_count inputs
        if ($request->has('product_counts')) {
            $productCounts = $request->input('product_counts');
            $totalGood = 0;
            $totalReject = 0;
            foreach($productCounts as &$pc) {
                $totalGood += (int)($pc['good_count'] ?? 0);
                $totalReject += (int)($pc['reject_count'] ?? 0);
                
                // Inject target/ideal stats
                if (isset($pc['product_id']) && isset($productTargets[$pc['product_id']])) {
                    $pc['target_output'] = $productTargets[$pc['product_id']];
                    $pc['ideal_rate'] = $productIdealRates[$pc['product_id']] ?? 0;
                }
            }
            $metadata['product_counts'] = $productCounts;
            $metadata['good_count'] = $totalGood;
            $metadata['reject_count'] = $totalReject;
        } else {
            // Fallback to simple inputs if no detailed counts provided
            if ($request->has('good_count')) $metadata['good_count'] = $request->input('good_count');
            if ($request->has('reject_count')) $metadata['reject_count'] = $request->input('reject_count');
        }
        
        $metadata['total_output'] = ($metadata['good_count'] ?? 0) + ($metadata['reject_count'] ?? 0);
        $metadata['target_output'] = $totalTarget;
        
        // Downtime Minutes (Total)
        $totalDowntimeMinutes = 0;
        if ($request->has('downtime_records')) {
             // Sum from the created/simulated events
             foreach($simulatedDowntimeEvents as $dt) {
                 $s = \Carbon\Carbon::parse($dt['start']);
                 $e = \Carbon\Carbon::parse($dt['end']);
                 $totalDowntimeMinutes += $s->diffInMinutes($e);
             }
        } elseif ($request->has('downtime_minutes')) {
            $totalDowntimeMinutes = $request->input('downtime_minutes');
        } else {
            // Keep existing or recalculate
             $totalDowntimeMinutes = $shift->downtimeEvents()->sum('duration_seconds') / 60;
        }
        $metadata['downtime_minutes'] = round($totalDowntimeMinutes);
        
        if ($request->has('material_loss_units')) $metadata['material_loss_units'] = $request->input('material_loss_units');
        if ($request->has('comment')) $metadata['edit_comment'] = $request->input('comment');


        // Audit Log
        if (!isset($metadata['edit_history'])) $metadata['edit_history'] = [];
        $metadata['edit_history'][] = [
            'edited_by' => $user->id,
            'edited_by_name' => $user->name,
            'edited_at' => now()->toISOString(),
            'changes' => 'Full Report Update', // Simplified for complex updates
            'comment' => $request->input('comment', ''),
        ];

        $shift->update([
             'metadata' => $metadata,
             'edited_by' => $user->id,
             'edited_at' => now(),
        ]);
        
        // 6. Regenerate Production Logs
        // Clear old logs for this shift
        \App\Models\ProductionLog::where('shift_id', $shift->shift_id)
            ->where('machine_id', $shift->machine_id)
            ->whereBetween('start_time', [$shift->started_at, $shift->ended_at])
            ->delete(); // This might be too aggressive if multiple shifts overlap? 
            // Better: Delete where shift_id matches OR created by this logic? 
            // For now, assuming OEE logs are 1:1 with Shift Report segments.
            
        // Create new logs
        $pcs = $metadata['product_counts'] ?? [];
        if (!empty($pcs)) {
            foreach ($pcs as $pc) {
                // Approximate timings if not in pc, use segments
                // For simplicity, we find the segment for this product
                // Or if passed in PC
                $logStart = isset($pc['start_time']) ? \Carbon\Carbon::parse($pc['start_time']) : $shift->started_at; 
                $logEnd = isset($pc['end_time']) ? \Carbon\Carbon::parse($pc['end_time']) : $shift->ended_at;
                
                \App\Models\ProductionLog::create([
                    'machine_id' => $shift->machine_id,
                    'product_id' => $pc['product_id'],
                    'shift_id' => $shift->shift_id,
                    'start_time' => $logStart,
                    'end_time' => $logEnd,
                    'good_count' => $pc['good_count'] ?? 0,
                    'reject_count' => $pc['reject_count'] ?? 0,
                ]);
            }
        } else {
             \App\Models\ProductionLog::create([
                'machine_id' => $shift->machine_id,
                'product_id' => $shift->product_id,
                'shift_id' => $shift->shift_id,
                'start_time' => $shift->started_at,
                'end_time' => $shift->ended_at,
                'good_count' => $metadata['good_count'],
                'reject_count' => $metadata['reject_count'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shift report updated successfully.',
            'shift' => $shift->fresh()
        ]);
    }

    /**
     * Get edit history for a specific shift
     */
    public function editHistory(int $shiftId)
    {
        $user = Auth::user();
        
        $shift = ProductionShift::with(['machine.line', 'editor', 'user'])->findOrFail($shiftId);
        
        // Permission Check
        if (!$user->canManagePlant($shift->machine->line->plant_id)) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }
        
        $metadata = $shift->metadata ?? [];
        $editHistory = $metadata['edit_history'] ?? [];
        
        return response()->json([
            'shift_id' => $shift->id,
            'started_by' => [
                'id' => $shift->user->id,
                'name' => $shift->user->name,
            ],
            'started_at' => $shift->started_at->toISOString(),
            'last_edited_by' => $shift->editor ? [
                'id' => $shift->editor->id,
                'name' => $shift->editor->name,
            ] : null,
            'last_edited_at' => $shift->edited_at?->toISOString(),
            'edit_history' => $editHistory,
        ]);
    }

    /**
     * Log a downtime event for the active shift
     */
    public function logDowntime(Request $request, int $machineId)
    {
        // Permission Check
        $user = Auth::user();
        $machine = \App\Models\Machine::with('line')->findOrFail($machineId);
        if (!$user->canManagePlant($machine->line->plant_id)) {
            return response()->json(['error' => 'Unauthorized for this plant.'], 403);
        }

        // Verify active shift exists
        $activeShift = ProductionShift::where('machine_id', $machineId)
            ->where('status', 'active')
            ->first();

        if (!$activeShift) {
            return response()->json(['error' => 'No active shift found. Cannot log downtime.'], 400);
        }

        $eventsToCreate = [];

        // Check for bulk events
        if ($request->has('events') && is_array($request->input('events'))) {
            $request->validate([
                'events' => 'required|array|min:1',
                'events.*.reason_code_id' => 'required|exists:reason_codes,id',
                'events.*.start_time' => 'required|date',
                'events.*.end_time' => 'required|date|after:events.*.start_time',
                'events.*.comment' => 'nullable|string|max:255',
            ]);
            $eventsToCreate = $request->input('events');
        } else {
            // Single event fallback
            $request->validate([
                'reason_code_id' => 'required|exists:reason_codes,id',
                'start_time' => 'required|date',
                'end_time' => 'required|date|after:start_time',
                'comment' => 'nullable|string|max:255',
            ]);
            $eventsToCreate[] = $request->only(['reason_code_id', 'start_time', 'end_time', 'comment']);
        }

        $createdEvents = [];
        \DB::transaction(function () use ($eventsToCreate, $machineId, $activeShift, &$createdEvents) {
            foreach ($eventsToCreate as $eventData) {
                // Parse the datetime strings
                $startTime = \Carbon\Carbon::parse($eventData['start_time']);
                $endTime = \Carbon\Carbon::parse($eventData['end_time']);
                
                // Calculate duration in seconds
                $durationSeconds = $startTime->diffInSeconds($endTime);

                // Create Event
                $createdEvents[] = \App\Models\DowntimeEvent::create([
                    'machine_id' => $machineId,
                    'reason_code_id' => $eventData['reason_code_id'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_seconds' => $durationSeconds,
                    'comment' => $eventData['comment'] ?? null,
                    'production_shift_id' => $activeShift->id 
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($createdEvents) > 1 
                ? count($createdEvents) . ' downtime events logged successfully.' 
                : 'Downtime logged successfully.',
            'events' => $createdEvents,
            'event' => $createdEvents[0] ?? null // Backward compatibility
        ]);
    }

    
    /**
     * Record a product changeover during an active shift
     */
    public function recordChangeover(Request $request, int $shiftId)
    {
        $user = Auth::user();
        
        // Find the shift and verify it's active
        $shift = ProductionShift::with('machine.line')->findOrFail($shiftId);

        // Permission Check
        if (!$user->canManagePlant($shift->machine->line->plant_id)) {
            return response()->json(['error' => 'Unauthorized for this plant.'], 403);
        }
        
        if ($shift->status !== 'active') {
            return response()->json([
                'error' => 'Can only record changeovers for active shifts.',
            ], 400);
        }
        
        // Validate request
        $request->validate([
            'to_product_id' => 'required|exists:products,id',
            'batch_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);
        
        // Get current product (could be from shift or last changeover)
        $lastChangeover = $shift->productChangeovers()->latest('changed_at')->first();
        $fromProductId = $lastChangeover ? $lastChangeover->to_product_id : $shift->product_id;
        
        // Create changeover record
        $changeover = \App\Models\ProductChangeover::create([
            'production_shift_id' => $shiftId,
            'from_product_id' => $fromProductId,
            'to_product_id' => $request->to_product_id,
            'changed_at' => now(),
            'recorded_by' => $user->id,
            'notes' => $request->notes,
        ]);
        
        // Update the shift's batch number if provided
        if ($request->filled('batch_number')) {
            $shift->batch_number = $request->batch_number;
            $shift->save();
        }
        
        // Load relationships for response
        $changeover->load(['fromProduct', 'toProduct', 'recordedBy']);
        
        return response()->json([
            'success' => true,
            'message' => 'Product changeover recorded successfully.',
            'changeover' => [
                'id' => $changeover->id,
                'from_product' => [
                   'id' => $changeover->fromProduct->id,
                    'name' => $changeover->fromProduct->name,
                ],
                'to_product' => [
                    'id' => $changeover->toProduct->id,
                    'name' => $changeover->toProduct->name,
                ],
                'changed_at' => $changeover->changed_at->toISOString(),
                'recorded_by' => $changeover->recordedBy->name,
                'notes' => $changeover->notes,
                'batch_number' => $shift->batch_number,
            ],
        ]);
    }

    /**
     * Get shift activity feed (downtime, losses, changeovers)
     */
    public function getActivity(int $shiftId)
    {
        $shift = ProductionShift::with([
            'downtimeEvents.reasonCode',
            'productChangeovers.fromProduct',
            'productChangeovers.toProduct',
        ])->find($shiftId);

        if (!$shift) {
            return response()->json([
                'success' => false,
                'error' => 'Shift not found'
            ], 404);
        }

        $activity = collect();

        // Add downtime events - query by production_shift_id
        $downtimeEvents = \App\Models\DowntimeEvent::with('reasonCode')
            ->where('production_shift_id', $shiftId)
            ->get();
            
        foreach ($downtimeEvents as $event) {
            $durationMin = round(($event->duration_seconds ?? 0) / 60);
            $activity->push([
                'id' => 'downtime-' . $event->id,
                'type' => 'downtime',
                'title' => $event->reasonCode?->description ?? $event->reasonCode?->name ?? 'Downtime',
                'description' => $durationMin . ' min',
                'timestamp' => $event->start_time?->toISOString() ?? $event->created_at->toISOString(),
            ]);
        }

        // Add material losses - query by machine_id and time range since no production_shift_id FK exists
        $losses = \App\Models\MaterialLoss::with('category')
            ->where('machine_id', $shift->machine_id)
            ->where('occurred_at', '>=', $shift->started_at)
            ->when($shift->ended_at, function($q) use ($shift) {
                return $q->where('occurred_at', '<=', $shift->ended_at);
            })
            ->get();
            
        foreach ($losses as $loss) {
            $activity->push([
                'id' => 'loss-' . $loss->id,
                'type' => 'loss',
                'title' => $loss->category?->name ?? 'Material Loss',
                'description' => $loss->quantity . ' ' . ($loss->unit ?? 'units'),
                'timestamp' => $loss->occurred_at?->toISOString() ?? $loss->created_at->toISOString(),
            ]);
        }

        // Add product changeovers
        foreach ($shift->productChangeovers as $changeover) {
            $activity->push([
                'id' => 'changeover-' . $changeover->id,
                'type' => 'changeover',
                'title' => 'Product Changed',
                'description' => 'Now producing: ' . ($changeover->toProduct?->name ?? 'Unknown'),
                'timestamp' => $changeover->changed_at?->toISOString() ?? $changeover->created_at->toISOString(),
            ]);
        }

        // Sort by timestamp descending (newest first)
        $sortedActivity = $activity->sortByDesc('timestamp')->values();

        return response()->json([
            'success' => true,
            'activity' => $sortedActivity,
        ]);
    }
    
    /**
     * Calculate quality score including material loss
     */
    private function calculateQualityScore(int $goodCount, int $rejectCount, float $materialLoss): float
    {
        $totalUnits = $goodCount + $rejectCount + $materialLoss;
        
        if ($totalUnits == 0) {
            return 100.0;
        }
        
        return round(($goodCount / $totalUnits) * 100, 2);
    }

    /**
     * Get the list of products that were produced during a shift
     * Returns product runs with durations
     */
    public function getProductRuns(int $machineId)
    {
        $activeShift = ProductionShift::where('machine_id', $machineId)
            ->where('status', 'active')
            ->with(['product', 'productChangeovers.fromProduct', 'productChangeovers.toProduct'])
            ->first();

        if (!$activeShift) {
            return response()->json([
                'success' => false,
                'error' => 'No active shift found',
            ], 404);
        }

        $productRuns = [];
        $changeovers = $activeShift->productChangeovers->sortBy('changed_at');
        
        // Start with the initial product
        $currentProduct = $activeShift->product;
        $currentStart = $activeShift->started_at;
        
        foreach ($changeovers as $changeover) {
            // Add run for previous product
            $endTime = $changeover->changed_at;
            $durationMinutes = $currentStart->diffInMinutes($endTime);
            
            $productRuns[] = [
                'product_id' => $currentProduct->id,
                'product_name' => $currentProduct->name,
                'start_time' => $currentStart->toISOString(),
                'end_time' => $endTime->toISOString(),
                'duration_minutes' => $durationMinutes,
            ];
            
            // Move to next product
            $currentProduct = $changeover->toProduct;
            $currentStart = $changeover->changed_at;
        }
        
        // Add final product run (up to now)
        $endTime = now();
        $durationMinutes = $currentStart->diffInMinutes($endTime);
        
        $productRuns[] = [
            'product_id' => $currentProduct->id,
            'product_name' => $currentProduct->name,
            'start_time' => $currentStart->toISOString(),
            'end_time' => $endTime->toISOString(),
            'duration_minutes' => $durationMinutes,
        ];

        return response()->json([
            'success' => true,
            'shift_id' => $activeShift->id,
            'has_changeovers' => $changeovers->count() > 0,
            'product_runs' => $productRuns,
        ]);
    }
}
