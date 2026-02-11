<?php

namespace App\Http\Controllers;

use App\Models\MaterialLoss;
use App\Models\MaterialLossCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MaterialLossController extends Controller
{
    public function index(Request $request)
    {
        $query = MaterialLoss::with(['category', 'shift', 'product', 'machine', 'recorder']);
        
        // Filters
        if ($request->has('category_id')) {
            $query->where('loss_category_id', $request->category_id);
        }
        
        if ($request->has('machine_id')) {
            $query->where('machine_id', $request->machine_id);
        }
        
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->forPeriod($request->start_date, $request->end_date);
        }
        
        $losses = $query->orderBy('occurred_at', 'desc')->paginate(20);
        $categories = MaterialLossCategory::active()->get();
        
        return Inertia::render('MaterialLoss/Index', [
            'losses' => $losses,
            'categories' => $categories,
            'filters' => $request->only(['category_id', 'machine_id', 'start_date', 'end_date']),
        ]);
    }

    public function store(Request $request)
    {
        // Handle bulk logging
        if ($request->has('losses')) {
            $validated = $request->validate([
                'losses' => 'required|array|min:1',
                'losses.*.loss_category_id' => 'required|exists:material_loss_categories,id',
                'losses.*.loss_type' => 'required|in:raw_material,packaging,other',
                'losses.*.quantity' => 'required|numeric|min:0',
                'losses.*.unit' => 'required|string',
                'losses.*.occurred_at' => 'required|date',
                'losses.*.shift_id' => 'nullable|exists:production_shifts,id',
                'losses.*.product_id' => 'nullable|exists:products,id',
                'losses.*.machine_id' => 'nullable|exists:machines,id',
                'losses.*.reason' => 'nullable|string|max:1000',
                'losses.*.notes' => 'nullable|string|max:1000',
                'losses.*.cost_estimate' => 'nullable|numeric|min:0',
            ]);

            \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
                foreach ($validated['losses'] as $lossData) {
                    $lossData['recorded_by'] = Auth::id();
                    
                    // Check logic for required reason if needed
                    $category = MaterialLossCategory::find($lossData['loss_category_id']);
                    if ($category->requires_reason && empty($lossData['reason'])) {
                        // Skip validation here implicitly or throw error, mostly frontend handles this
                    }

                    MaterialLoss::create($lossData);
                }
            });

            return back()->with('success', count($validated['losses']) . ' material losses recorded successfully.');
        }

        // Handle single logging (Legacy/Fallback)
        $validated = $request->validate([
            'loss_category_id' => 'required|exists:material_loss_categories,id',
            'loss_type' => 'required|in:raw_material,packaging,other',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string',
            'occurred_at' => 'required|date',
            'shift_id' => 'nullable|exists:production_shifts,id',
            'product_id' => 'nullable|exists:products,id',
            'machine_id' => 'nullable|exists:machines,id',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'cost_estimate' => 'nullable|numeric|min:0',
        ]);
        
        // Permission Check
        if (isset($validated['machine_id'])) {
             $machine = \App\Models\Machine::with('line')->find($validated['machine_id']);
             if ($machine && !Auth::user()->canManagePlant($machine->line->plant_id)) {
                  abort(403, 'Unauthorized for this plant.');
             }
        }

        // Check if reason is required for this category
        $category = MaterialLossCategory::find($validated['loss_category_id']);
        if ($category->requires_reason && empty($validated['reason'])) {
            return back()->withErrors(['reason' => 'Reason is required for this loss category.']);
        }
        
        $validated['recorded_by'] = Auth::id();
        
        // MaterialLoss model will auto-calculate finished_units_lost on save
        $loss = MaterialLoss::create($validated);
        
        return back()->with('success', 'Material loss recorded successfully.');
    }

    public function update(Request $request, MaterialLoss $loss)
    {
        $validated = $request->validate([
            'quantity' => 'sometimes|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'cost_estimate' => 'nullable|numeric|min:0',
        ]);
        
        $loss->update($validated);
        
        return back()->with('success', 'Material loss updated successfully.');
    }

    public function destroy(MaterialLoss $loss)
    {
        // Only admins can delete
        if (!Auth::user()->groups()->whereIn('name', ['Admin'])->exists()) {
            abort(403, 'Only administrators can delete material loss records.');
        }
        
        $loss->delete();
        
        return back()->with('success', 'Material loss record deleted.');
    }

    public function batchStore(Request $request)
    {
        // Sanitize incoming data - convert invalid IDs to null
        $losses = collect($request->input('losses', []))->map(function ($loss) {
            // Convert negative IDs to null (they are placeholder values)
            if (isset($loss['machine_id']) && $loss['machine_id'] < 1) {
                $loss['machine_id'] = null;
            }
            if (isset($loss['shift_id']) && $loss['shift_id'] < 1) {
                $loss['shift_id'] = null;
            }
            if (isset($loss['product_id']) && $loss['product_id'] < 1) {
                $loss['product_id'] = null;
            }
            return $loss;
        })->toArray();
        
        $request->merge(['losses' => $losses]);
        
        $validated = $request->validate([
            'losses' => 'required|array',
            'losses.*.loss_category_id' => 'required|exists:material_loss_categories,id',
            'losses.*.quantity' => 'required|numeric|min:0',
            'losses.*.unit' => 'required|string',
            'losses.*.occurred_at' => 'nullable|date',
            'losses.*.shift_id' => 'nullable|exists:production_shifts,id',
            'losses.*.product_id' => 'nullable|exists:products,id',
            'losses.*.machine_id' => 'nullable|exists:machines,id',
            'losses.*.reason' => 'nullable|string',
            'losses.*.notes' => 'nullable|string',
            'losses.*.cost_estimate' => 'nullable|numeric|min:0',
        ]);
        
        // Permission Check for Bulk
        $machineIds = collect($validated['losses'])->pluck('machine_id')->filter()->unique();
        if ($machineIds->isNotEmpty()) {
            $machines = \App\Models\Machine::with('line')->whereIn('id', $machineIds)->get();
            foreach ($machines as $machine) {
                if (!Auth::user()->canManagePlant($machine->line->plant_id)) {
                     abort(403, 'Unauthorized for this plant.');
                }
            }
        }
        
        foreach ($validated['losses'] as $lossData) {
            $lossData['recorded_by'] = Auth::id();
            
            // Get loss_type from category
            $category = MaterialLossCategory::find($lossData['loss_category_id']);
            $categoryLossType = $category?->loss_type ?? 'other';
            
            // Normalize loss_type to match enum constraint (replace spaces with underscores)
            $lossData['loss_type'] = str_replace(' ', '_', strtolower($categoryLossType));
            
            // Ensure it's one of the valid enum values
            if (!in_array($lossData['loss_type'], ['raw_material', 'packaging', 'other'])) {
                $lossData['loss_type'] = 'other';
            }
            
            // Set occurred_at if not provided
            if (empty($lossData['occurred_at'])) {
                $lossData['occurred_at'] = now();
            }
            
            // MaterialLoss model will auto-calculate finished_units_lost on save
            MaterialLoss::create($lossData);
        }
        
        return back()->with('success', count($validated['losses']) . ' material losses recorded.');
    }

    public function summary(Request $request)
    {
        $query = MaterialLoss::query();
        
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->forPeriod($request->start_date, $request->end_date);
        }
        
        if ($request->has('machine_id')) {
            $query->where('machine_id', $request->machine_id);
        }
        
        // Use finished_units_lost for accurate totals
        $totalFinishedUnitsLost = $query->sum('finished_units_lost');
        $totalCost = $query->sum('cost_estimate');
        $lossCount = $query->count();
        
        // Group by category with finished units
        $byCategory = MaterialLoss::selectRaw('loss_category_id, SUM(finished_units_lost) as total_finished_units, COUNT(*) as count')
            ->whereNotNull('occurred_at')
            ->groupBy('loss_category_id')
            ->with('category')
            ->get();
        
        return response()->json([
            'total_finished_units_lost' => $totalFinishedUnitsLost,
            'total_cost' => $totalCost,
            'loss_count' => $lossCount,
            'by_category' => $byCategory,
        ]);
    }

    /**
     * API: Get all active categories for dropdown
     */
    public function apiCategories()
    {
        $categories = MaterialLossCategory::active()->get(['id', 'name', 'loss_type', 'requires_reason', 'affects_oee']);
        return response()->json(['categories' => $categories]);
    }

    /**
     * API: Store material loss from Operator kiosk
     */
    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:material_loss_categories,id',
            'shift_id' => 'nullable|exists:production_shifts,id',
            'machine_id' => 'nullable|exists:machines,id',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        // Permission Check
        if (isset($validated['machine_id'])) {
             $machine = \App\Models\Machine::with('line')->find($validated['machine_id']);
             if ($machine && !$request->user()->canManagePlant($machine->line->plant_id)) {
                  return response()->json(['error' => 'Unauthorized for this plant.'], 403);
             }
        }
        
        $category = MaterialLossCategory::find($validated['category_id']);
        
        // Check if reason is required
        if ($category->requires_reason && empty($validated['reason'])) {
            return response()->json(['error' => 'Reason is required for this loss category.'], 422);
        }
        
        // Normalize loss_type to match enum constraint (replace spaces with underscores)
        $lossType = str_replace(' ', '_', strtolower($category->loss_type ?? 'other'));
        if (!in_array($lossType, ['raw_material', 'packaging', 'other'])) {
            $lossType = 'other';
        }
        
        $loss = MaterialLoss::create([
            'loss_category_id' => $validated['category_id'],
            'loss_type' => $lossType,
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'],
            'occurred_at' => now(),
            'shift_id' => $validated['shift_id'] ?? null,
            'machine_id' => $validated['machine_id'] ?? null,
            'reason' => $validated['reason'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => Auth::id(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Material loss recorded successfully.',
            'loss' => $loss,
        ]);
    }
}
