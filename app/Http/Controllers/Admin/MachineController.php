<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'line_id' => 'required|exists:lines,id',
            'default_ideal_rate' => 'nullable|numeric|min:0',
        ]);

        Machine::create($validated);

        return redirect()->back()->with('success', 'Machine created.');
    }

    public function update(Request $request, Machine $machine)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'default_ideal_rate' => 'nullable|numeric|min:0',
            'status' => 'required|string',
        ]);

        $machine->update($validated);

        return redirect()->back()->with('success', 'Machine updated.');
    }

    public function show(Machine $machine)
    {
        $machine->load('line');
        // Render OEE Dashboard focused on this machine
        return \Inertia\Inertia::render('Dashboard', [
            'initialContext' => [
                'plantId' => $machine->line->plant_id,
                'lineId' => $machine->line_id,
                'machineId' => $machine->id,
            ]
        ]);
    }

    public function destroy(Machine $machine)
    {
        $machine->delete();
        return redirect()->back()->with('success', 'Machine deleted.');
    }
    public function attachShift(Request $request, Machine $machine)
    {
         $validated = $request->validate([
            'shift_id' => 'required|exists:shifts,id',
        ]);

        $machine->shifts()->syncWithoutDetaching([$validated['shift_id']]);

        return redirect()->back()->with('success', 'Shift assigned to machine.');
    }

    public function detachShift(Machine $machine, $shiftId)
    {
        $machine->shifts()->detach($shiftId);
        return redirect()->back()->with('success', 'Shift removed from machine.');
    }

    public function assignProduct(Request $request, Machine $machine)
    {
        if (!$request->user()->hasPermission('products.edit')) {
            abort(403, 'Unauthorized to assign products.');
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'ideal_rate' => 'required|numeric|min:0',
        ]);

        $machine->machineProductConfigs()->updateOrCreate(
            ['product_id' => $validated['product_id']],
            ['ideal_rate' => $validated['ideal_rate']]
        );

        return redirect()->back()->with('success', 'Product configuration saved.');
    }

    public function detachProduct(Request $request, Machine $machine, $productId)
    {
        if (!$request->user()->hasPermission('products.edit')) {
            abort(403, 'Unauthorized to remove product assignment.');
        }

        $machine->machineProductConfigs()->where('product_id', $productId)->delete();
        return redirect()->back()->with('success', 'Product configuration removed.');
    }

    public function assignReason(Request $request, Machine $machine)
    {
        $validated = $request->validate([
            'reason_code_id' => 'required|exists:reason_codes,id',
        ]);

        $machine->reasonCodes()->syncWithoutDetaching([$validated['reason_code_id']]);

        return redirect()->back()->with('success', 'Reason code assigned to machine.');
    }

    public function detachReason(Machine $machine, $reasonCodeId)
    {
        $machine->reasonCodes()->detach($reasonCodeId);
        return redirect()->back()->with('success', 'Reason code removed from machine.');
    }
}
