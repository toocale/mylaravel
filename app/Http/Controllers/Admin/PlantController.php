<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use Illuminate\Http\Request;

class PlantController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'organization_id' => 'required|exists:organizations,id',
        ]);

        Plant::create($validated);

        return redirect()->back()->with('success', 'Plant created.');
    }

    public function update(Request $request, Plant $plant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        $plant->update($validated);

        return redirect()->back()->with('success', 'Plant updated.');
    }

    public function destroy(Plant $plant)
    {
        $plant->delete();
        return redirect()->back()->with('success', 'Plant deleted.');
    }
}
