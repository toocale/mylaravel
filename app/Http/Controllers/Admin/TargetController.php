<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductionTarget;
use App\Models\Machine;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TargetController extends Controller
{
    /**
     * Display a listing of production targets
     */
    public function index(Request $request)
    {
        $query = ProductionTarget::with(['machine.line.plant', 'shift', 'creator', 'updater'])
            ->orderBy('created_at', 'desc');

        // Filter by machine
        if ($request->has('machine_id') && $request->machine_id) {
            $query->where('machine_id', $request->machine_id);
        }

        // Filter by shift
        if ($request->has('shift_id') && $request->shift_id) {
            $query->where('shift_id', $request->shift_id);
        }

        // Filter by active status
        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        $targets = $query->paginate(20);

        // Get machines and shifts for filters
        $machines = Machine::with('line.plant')
            ->orderBy('name')
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'line_name' => $m->line->name,
                'plant_name' => $m->line->plant->name,
            ]);

        $shifts = Shift::orderBy('name')->get();

        return Inertia::render('Admin/Targets/Index', [
            'targets' => $targets,
            'machines' => $machines,
            'shifts' => $shifts,
            'filters' => $request->only(['machine_id', 'shift_id', 'active_only']),
        ]);
    }

    /**
     * Show the form for creating a new target
     */
    public function create()
    {
        $machines = Machine::with('line.plant')
            ->orderBy('name')
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'line_name' => $m->line->name,
                'plant_name' => $m->line->plant->name,
                'display_name' => "{$m->line->plant->name} > {$m->line->name} > {$m->name}",
            ]);

        $shifts = Shift::orderBy('name')->get();

        return Inertia::render('Admin/Targets/Create', [
            'machines' => $machines,
            'shifts' => $shifts,
        ]);
    }

    /**
     * Store a newly created target
     */
    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('targets.create')) {
            abort(403, 'Unauthorized to create targets.');
        }
        $validated = $request->validate([
            'machine_id' => 'nullable|exists:machines,id',
            'line_id' => 'nullable|exists:lines,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'target_oee' => 'nullable|numeric|min:0|max:100',
            'target_availability' => 'nullable|numeric|min:0|max:100',
            'target_performance' => 'nullable|numeric|min:0|max:100',
            'target_quality' => 'nullable|numeric|min:0|max:100',
            'target_units' => 'nullable|integer|min:0',
            'target_good_units' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (empty($validated['machine_id']) && empty($validated['line_id'])) {
            return back()->withErrors(['targetLevel' => 'Please select either a Line or a Machine.']);
        }

        // Check for overlapping targets
        $query = ProductionTarget::query();
        
        if (!empty($validated['machine_id'])) {
            $query->where('machine_id', $validated['machine_id']);
        } else {
            $query->where('line_id', $validated['line_id']);
        }

        $overlapping = $query->where('shift_id', $validated['shift_id'] ?? null)
            ->where(function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    // New target starts during existing target
                    $q->where('effective_from', '<=', $validated['effective_from'])
                      ->where(function ($subQ) use ($validated) {
                          $subQ->whereNull('effective_to')
                               ->orWhere('effective_to', '>=', $validated['effective_from']);
                      });
                })->orWhere(function ($q) use ($validated) {
                    // New target ends during existing target
                    if (isset($validated['effective_to'])) {
                        $q->where('effective_from', '<=', $validated['effective_to'])
                          ->where(function ($subQ) use ($validated) {
                              $subQ->whereNull('effective_to')
                                   ->orWhere('effective_to', '>=', $validated['effective_to']);
                          });
                    }
                });
            })
            ->exists();

        if ($overlapping) {
            return back()->withErrors([
                'effective_from' => 'A target already exists for this machine and shift during the specified period.'
            ]);
        }

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $target = ProductionTarget::create($validated);

        return back()->with('success', 'Production target created successfully.');
    }

    /**
     * Show the form for editing the specified target
     */
    public function edit(ProductionTarget $target)
    {
        $target->load(['machine.line.plant', 'shift']);

        $machines = Machine::with('line.plant')
            ->orderBy('name')
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'line_name' => $m->line->name,
                'plant_name' => $m->line->plant->name,
                'display_name' => "{$m->line->plant->name} > {$m->line->name} > {$m->name}",
            ]);

        $shifts = Shift::orderBy('name')->get();

        return Inertia::render('Admin/Targets/Edit', [
            'target' => $target,
            'machines' => $machines,
            'shifts' => $shifts,
        ]);
    }

    /**
     * Update the specified target
     */
    public function update(Request $request, ProductionTarget $target)
    {
        if (!$request->user()->hasPermission('targets.edit')) {
            abort(403, 'Unauthorized to edit targets.');
        }
        $validated = $request->validate([
            'machine_id' => 'nullable|exists:machines,id',
            'line_id' => 'nullable|exists:lines,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'target_oee' => 'nullable|numeric|min:0|max:100',
            'target_availability' => 'nullable|numeric|min:0|max:100',
            'target_performance' => 'nullable|numeric|min:0|max:100',
            'target_quality' => 'nullable|numeric|min:0|max:100',
            'target_units' => 'nullable|integer|min:0',
            'target_good_units' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (empty($validated['machine_id']) && empty($validated['line_id'])) {
             // Fallback to existing if not provided to allow partial updates if needed, though usually form sends all
             if (!$target->machine_id && !$target->line_id) {
                 return back()->withErrors(['targetLevel' => 'Target must be associated with a Line or Machine.']);
             }
        }

        // Check for overlapping targets (excluding current target)
        $query = ProductionTarget::where('id', '!=', $target->id);
        
        $machineId = $validated['machine_id'] ?? $target->machine_id;
        $lineId = $validated['line_id'] ?? $target->line_id;

        // Ensure we check against the correct association
        if ($machineId) {
             $query->where('machine_id', $machineId);
        } elseif ($lineId) {
             $query->where('line_id', $lineId);
        }

        $overlapping = $query->where('shift_id', $validated['shift_id'] ?? null)
            ->where(function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('effective_from', '<=', $validated['effective_from'])
                      ->where(function ($subQ) use ($validated) {
                          $subQ->whereNull('effective_to')
                               ->orWhere('effective_to', '>=', $validated['effective_from']);
                      });
                })->orWhere(function ($q) use ($validated) {
                    if (isset($validated['effective_to'])) {
                        $q->where('effective_from', '<=', $validated['effective_to'])
                          ->where(function ($subQ) use ($validated) {
                              $subQ->whereNull('effective_to')
                                   ->orWhere('effective_to', '>=', $validated['effective_to']);
                          });
                    }
                });
            })
            ->exists();

        if ($overlapping) {
            return back()->withErrors([
                'effective_from' => 'A target already exists for this context and shift during the specified period.'
            ]);
        }

        $validated['updated_by'] = Auth::id();

        $target->update($validated);

        return back()->with('success', 'Production target updated successfully.');
    }

    /**
     * Remove the specified target
     */
    public function destroy(ProductionTarget $target)
    {
        if (!$request->user()->hasPermission('targets.delete')) {
            abort(403, 'Unauthorized to delete targets.');
        }
        $target->delete();

        return back()->with('success', 'Production target deleted successfully.');
    }

    /**
     * Get active target for a machine and shift
     */
    public function getActiveTarget(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'date' => 'nullable|date',
        ]);

        $target = ProductionTarget::getApplicableTarget(
            $validated['machine_id'],
            $validated['shift_id'] ?? null,
            $validated['date'] ?? null
        );

        return response()->json([
            'target' => $target,
        ]);
    }
}
