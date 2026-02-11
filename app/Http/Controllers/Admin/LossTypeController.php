<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LossType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LossTypeController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'code' => [
                'required', 
                'string', 
                'max:50', 
                Rule::unique('loss_types', 'code')
            ],
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'affects_oee' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        LossType::create([
            'organization_id' => $validated['organization_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#ef4444',
            'affects_oee' => $validated['affects_oee'] ?? false,
            'active' => $validated['active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->back()->with('success', 'Loss type created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LossType $lossType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required', 
                'string', 
                'max:50', 
                Rule::unique('loss_types', 'code')->ignore($lossType->id)
            ],
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'affects_oee' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $lossType->update($validated);

        return redirect()->back()->with('success', 'Loss type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LossType $lossType)
    {
        $lossType->delete();

        return redirect()->back()->with('success', 'Loss type deleted successfully.');
    }
}
