<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\ProductionLog;
use App\Models\DowntimeEvent;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class IngestionController extends Controller
{
    protected $shiftService;

    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    public function production(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'product_id' => 'required|exists:products,id',
            'good_count' => 'required|integer|min:0',
            'reject_count' => 'integer|min:0',
            'timestamp' => 'date', // Optional
        ]);

        $machine = Machine::with('line.plant')->findOrFail($validated['machine_id']);
        $timestamp = $request->has('timestamp') ? Carbon::parse($request->timestamp) : now();

        // Identify Shift
        $shift = $this->shiftService->getShiftForTime($machine->line->plant, $timestamp);

        // Security Check for Kiosk Users (if logged in via browser)
        if ($request->user() && !$request->user()->canManagePlant($machine->line->plant_id)) {
             return response()->json(['error' => 'Unauthorized for this plant.'], 403);
        }

        $log = ProductionLog::create([
            'machine_id' => $machine->id,
            'product_id' => $validated['product_id'],
            'shift_id' => $shift?->id, // Nullable if no shift found (e.g. maintenance time)
            'good_count' => $validated['good_count'],
            'reject_count' => $request->input('reject_count', 0),
            'start_time' => $timestamp,
        ]);

        return response()->json([
            'message' => 'Production log recorded',
            'data' => $log
        ], 201);
    }

    public function downtime(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'reason_code_id' => 'nullable|exists:reason_codes,id',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'duration_seconds' => 'nullable|integer|min:0', // Optional override
        ]);

        $machine = Machine::with('line.plant')->findOrFail($validated['machine_id']);
        $startTime = Carbon::parse($validated['start_time']);
        
        // Security Check for Kiosk Users
        if ($request->user() && !$request->user()->canManagePlant($machine->line->plant_id)) {
             return response()->json(['error' => 'Unauthorized for this plant.'], 403);
        }

        $shift = $this->shiftService->getShiftForTime($machine->line->plant, $startTime);

        // Calc duration
        $duration = 0;
        if ($request->filled('duration_seconds')) {
            $duration = $request->duration_seconds;
        } elseif ($request->filled('end_time')) {
            $endTime = Carbon::parse($validated['end_time']);
            $duration = $endTime->diffInSeconds($startTime);
        }

        $event = DowntimeEvent::create([
            'machine_id' => $machine->id,
            'reason_code_id' => $validated['reason_code_id'],
            'shift_id' => $shift?->id,
            'start_time' => $startTime,
            'end_time' => $request->input('end_time'),
            'duration_seconds' => $duration,
        ]);

        return response()->json([
            'message' => 'Downtime event recorded',
            'data' => $event
        ], 201);
    }
}
