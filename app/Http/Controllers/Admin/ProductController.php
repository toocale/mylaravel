<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('products.create')) {
            abort(403, 'Unauthorized to create products.');
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'unit_of_measure' => 'nullable|string|max:50',
            'reference_weight' => 'nullable|numeric|min:0',
            'assign_to_machine_id' => 'nullable|exists:machines,id',
            'finished_unit' => 'nullable|string|max:50',
            'fill_volume' => 'nullable|numeric|min:0',
            'fill_volume_unit' => 'nullable|string|max:50',
        ]);
        
        $validated['organization_id'] = $request->user()->organization_id ?? 1; // Fallback for MVP

        $product = Product::create([
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'unit_of_measure' => $validated['unit_of_measure'] ?? null,
            'reference_weight' => $validated['reference_weight'] ?? null,
            'organization_id' => $validated['organization_id'],
            'finished_unit' => $validated['finished_unit'] ?? null,
            'fill_volume' => $validated['fill_volume'] ?? null,
            'fill_volume_unit' => $validated['fill_volume_unit'] ?? null,
        ]);

        if ($request->has('assign_to_machine_id') && $request->assign_to_machine_id) {
            $machine = \App\Models\Machine::find($request->assign_to_machine_id);
            if ($machine) {
                \App\Models\MachineProductConfig::create([
                    'machine_id' => $machine->id,
                    'product_id' => $product->id,
                    'ideal_rate' => $machine->default_ideal_rate ?? 0,
                ]);
                return redirect()->back()->with('success', 'Product created and assigned to machine.');
            }
        }

        return redirect()->back()->with('success', 'Product created.');
    }

    public function update(Request $request, Product $product)
    {
        if (!$request->user()->hasPermission('products.edit')) {
            abort(403, 'Unauthorized to edit products.');
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $product->id,
            'unit_of_measure' => 'nullable|string|max:50',
            'reference_weight' => 'nullable|numeric|min:0',
            'finished_unit' => 'nullable|string|max:50',
            'fill_volume' => 'nullable|numeric|min:0',
            'fill_volume_unit' => 'nullable|string|max:50',
        ]);

        $product->update($validated);

        return redirect()->back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        if (!$request->user()->hasPermission('products.delete')) {
            abort(403, 'Unauthorized to delete products.');
        }
        $product->delete();
        return redirect()->back()->with('success', 'Product deleted.');
    }
}
