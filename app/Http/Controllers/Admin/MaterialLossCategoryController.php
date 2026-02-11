<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialLossCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaterialLossCategoryController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required', 
                'string', 
                'max:20', 
                Rule::unique('material_loss_categories', 'code')
            ],
            'description' => 'nullable|string|max:500',
            'loss_type_id' => 'required|exists:loss_types,id',
            'affects_oee' => 'boolean',
            'requires_reason' => 'boolean',
            'color' => 'nullable|string|max:7',
            'active' => 'boolean',
        ]);

        // Get the legacy loss_type string from the related LossType model for backward compatibility
        $lossType = \App\Models\LossType::find($validated['loss_type_id']);

        MaterialLossCategory::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'loss_type_id' => $validated['loss_type_id'],
            'loss_type' => $lossType ? $lossType->code : 'other', // Fallback for legacy column
            'affects_oee' => $validated['affects_oee'] ?? false,
            'requires_reason' => $validated['requires_reason'] ?? false,
            'color' => $validated['color'] ?? '#ef4444',
            'active' => $validated['active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'Loss category created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MaterialLossCategory $materialLossCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required', 
                'string', 
                'max:20', 
                Rule::unique('material_loss_categories', 'code')->ignore($materialLossCategory->id)
            ],
            'description' => 'nullable|string|max:500',
            'loss_type_id' => 'required|exists:loss_types,id',
            'affects_oee' => 'boolean',
            'requires_reason' => 'boolean',
            'color' => 'nullable|string|max:7',
            'active' => 'boolean',
        ]);

        // Get the legacy loss_type string
        $lossType = \App\Models\LossType::find($validated['loss_type_id']);

        $materialLossCategory->update([
            ...$validated,
            'loss_type' => $lossType ? $lossType->code : 'other'
        ]);

        return redirect()->back()->with('success', 'Loss category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaterialLossCategory $materialLossCategory)
    {
        $materialLossCategory->delete();

        return redirect()->back()->with('success', 'Loss category deleted successfully.');
    }
}
