<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('shifts.create')) {
            abort(403, 'Unauthorized to create shifts.');
        }
        $validated = $request->validate([
            'plant_id' => 'required|exists:plants,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:day,night',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
        ]);

        Shift::create($validated);

        return redirect()->back()->with('success', 'Shift created.');
    }

    public function update(Request $request, Shift $shift)
    {
        if (!$request->user()->hasPermission('shifts.edit')) {
            abort(403, 'Unauthorized to edit shifts.');
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:day,night',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
        ]);

        $shift->update($validated);

        return redirect()->back()->with('success', 'Shift updated.');
    }

    public function destroy(Shift $shift)
    {
        if (!$request->user()->hasPermission('shifts.delete')) {
            abort(403, 'Unauthorized to delete shifts.');
        }
        $shift->delete();
        return redirect()->back()->with('success', 'Shift deleted.');
    }
}
