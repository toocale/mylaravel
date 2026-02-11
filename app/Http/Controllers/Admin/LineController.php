<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use Illuminate\Http\Request;

class LineController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'plant_id' => 'required|exists:plants,id',
        ]);

        Line::create($validated);

        return redirect()->back()->with('success', 'Line created.');
    }

    public function update(Request $request, Line $line)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $line->update($validated);

        return redirect()->back()->with('success', 'Line updated.');
    }

    public function show(Line $line)
    {
        // Render OEE Dashboard focused on this line
        return \Inertia\Inertia::render('Dashboard', [
            'initialContext' => [
                'plantId' => $line->plant_id,
                'lineId' => $line->id,
                'machineId' => null,
            ]
        ]);
    }

    public function destroy(Line $line)
    {
        $line->delete();
        return redirect()->back()->with('success', 'Line deleted.');
    }
}
