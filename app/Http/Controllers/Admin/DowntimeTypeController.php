<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DowntimeType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DowntimeTypeController extends Controller
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
                Rule::unique('downtime_types', 'code')
            ],
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'affects_availability' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        DowntimeType::create([
            'organization_id' => $validated['organization_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#6b7280',
            'affects_availability' => $validated['affects_availability'] ?? true,
            'active' => $validated['active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->back()->with('success', 'Downtime type created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DowntimeType $downtimeType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required', 
                'string', 
                'max:50', 
                Rule::unique('downtime_types', 'code')->ignore($downtimeType->id)
            ],
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'affects_availability' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $downtimeType->update($validated);

        return redirect()->back()->with('success', 'Downtime type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DowntimeType $downtimeType)
    {
        // Prevent deleting default system types
        if ($downtimeType->is_default) {
            return redirect()->back()->with('error', 'Cannot delete default system type.');
        }
        
        $downtimeType->delete();

        return redirect()->back()->with('success', 'Downtime type deleted successfully.');
    }
}
