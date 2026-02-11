<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReasonCode;
use Illuminate\Http\Request;

use Illuminate\Validation\Rule;

class ReasonCodeController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'code' => [
                'required', 
                'string', 
                'max:255', 
                Rule::unique('reason_codes')->where(fn ($query) => $query->where('organization_id', $request->organization_id))
            ],
            'description' => 'required|string|max:255',
            'downtime_type_id' => 'required|exists:downtime_types,id',
            'assign_to_machine_id' => 'nullable|exists:machines,id',
        ]);

        $downtimeType = \App\Models\DowntimeType::find($validated['downtime_type_id']);

        $reason = ReasonCode::create([
            'organization_id' => $validated['organization_id'],
            'code' => $validated['code'],
            'description' => $validated['description'],
            'downtime_type_id' => $validated['downtime_type_id'],
            'category' => $downtimeType ? $downtimeType->code : 'unplanned', // Legacy fallback
        ]);

        if ($request->has('assign_to_machine_id') && $request->assign_to_machine_id) {
            $machine = \App\Models\Machine::find($request->assign_to_machine_id);
            if ($machine) {
                $machine->reasonCodes()->syncWithoutDetaching([$reason->id]);
                return redirect()->back()->with('success', 'Reason code created and assigned to machine.');
            }
        }

        return redirect()->back()->with('success', 'Reason code created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReasonCode $reasonCode)
    {
        $validated = $request->validate([
            'code' => [
                'required', 
                'string', 
                'max:255', 
                Rule::unique('reason_codes')->ignore($reasonCode->id)->where(fn ($query) => $query->where('organization_id', $reasonCode->organization_id))
            ],
            'description' => 'required|string|max:255',
            'downtime_type_id' => 'required|exists:downtime_types,id',
        ]);

        $downtimeType = \App\Models\DowntimeType::find($validated['downtime_type_id']);

        $reasonCode->update([
            ...$validated,
            'category' => $downtimeType ? $downtimeType->code : 'unplanned'
        ]);

        return redirect()->back()->with('success', 'Reason code updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReasonCode $reasonCode)
    {
        $reasonCode->delete();

        return redirect()->back()->with('success', 'Reason code deleted successfully.');
    }
}
