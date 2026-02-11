<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnitConversion;
use Illuminate\Http\Request;

class UnitConversionController extends Controller
{
    /**
     * Get all unit conversions.
     */
    public function index()
    {
        $units = UnitConversion::orderBy('category')
            ->orderByDesc('is_base')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'units' => $units,
        ]);
    }

    /**
     * Store a new unit conversion.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:unit_conversions,code',
            'alias' => 'nullable|string|max:50',
            'category' => 'required|in:volume,weight,count',
            'to_base_factor' => 'required|numeric|min:0.000001',
            'base_unit_code' => 'required|string|max:50',
            'is_base' => 'boolean',
            'active' => 'boolean',
        ]);

        // If marking as base, unmark others in same category
        if ($request->boolean('is_base')) {
            UnitConversion::where('category', $validated['category'])
                ->update(['is_base' => false]);
        }

        $unit = UnitConversion::create($validated);
        UnitConversion::clearCache();

        return response()->json([
            'success' => true,
            'unit' => $unit,
            'message' => 'Unit conversion created successfully.',
        ]);
    }

    /**
     * Update an existing unit conversion.
     */
    public function update(Request $request, UnitConversion $unitConversion)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:unit_conversions,code,' . $unitConversion->id,
            'alias' => 'nullable|string|max:50',
            'category' => 'required|in:volume,weight,count',
            'to_base_factor' => 'required|numeric|min:0.000001',
            'base_unit_code' => 'required|string|max:50',
            'is_base' => 'boolean',
            'active' => 'boolean',
        ]);

        // If marking as base, unmark others in same category
        if ($request->boolean('is_base') && !$unitConversion->is_base) {
            UnitConversion::where('category', $validated['category'])
                ->where('id', '!=', $unitConversion->id)
                ->update(['is_base' => false]);
        }

        $unitConversion->update($validated);
        UnitConversion::clearCache();

        return response()->json([
            'success' => true,
            'unit' => $unitConversion->fresh(),
            'message' => 'Unit conversion updated successfully.',
        ]);
    }

    /**
     * Delete a unit conversion.
     */
    public function destroy(UnitConversion $unitConversion)
    {
        // Prevent deleting base units
        if ($unitConversion->is_base) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete a base unit. Please designate another unit as base first.',
            ], 422);
        }

        $unitConversion->delete();
        UnitConversion::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Unit conversion deleted successfully.',
        ]);
    }

    /**
     * Get units for dropdown (grouped by category).
     */
    public function dropdown(Request $request)
    {
        $category = $request->get('category');
        $units = UnitConversion::getForDropdown($category);

        return response()->json([
            'success' => true,
            'units' => $units,
        ]);
    }
}
