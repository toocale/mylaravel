<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ProductionTarget;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConfigurationController extends Controller
{
    public function index(Request $request)
    {
        // Get or auto-assign organization
        $organization = $request->user()->organization;
        
        // If user has no organization, auto-assign to first real organization
        if (!$organization) {
            \Log::info('User has no organization, auto-assigning', ['user_id' => $request->user()->id]);
            
            // Get first organization that is NOT "Default Org" (empty placeholder)
            $organization = Organization::where('name', '!=', 'Default Org')
                ->orderBy('created_at')
                ->first();
            
            // If still no organization found, create a default one or use any available
            if (!$organization) {
                $organization = Organization::first();
            }
            
            // If we found an organization, assign it to the user
            if ($organization) {
                $request->user()->organization_id = $organization->id;
                $request->user()->save();
                \Log::info('User assigned to organization', [
                    'user_id' => $request->user()->id,
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name
                ]);
            } else {
                // No organization exists at all - this should not happen in production
                return redirect()->route('dashboard')->with('error', 'No organization found. Please contact system administrator.');
            }
        }

        if ($request->user()->isAdmin()) {
            // Admin sees EVERYTHING
            // Fetch all organizations with their plants
            $allOrganizations = \App\Models\Organization::with([
                'plants.lines.machines.shifts',
                'plants.lines.machines',
                'plants.lines.machines.machineProductConfigs.product',
                'plants.lines.machines.reasonCodes',
                'plants.shifts',
            ])->get();

            // We still need flat lists for other props
            $plants = $allOrganizations->flatMap->plants;
            $products = \App\Models\Product::all();
            $downtimeTypes = \App\Models\DowntimeType::active()->ordered()->get();
            $lossTypes = \App\Models\LossType::active()->ordered()->get();
            $reasonCodes = \App\Models\ReasonCode::with('downtimeType')->get();
            $materialLossCategories = \App\Models\MaterialLossCategory::active()->with('lossType')->get();
            
            // Pass filtered orgs to view (only those with plants or the default one)
            $organizationsPayload = $allOrganizations; 
        } else {
            // Normal user: just their org
            $organization->load([
                'plants.lines.machines.shifts',
                'plants.lines.machines',
                'plants.lines.machines.machineProductConfigs.product',
                'plants.lines.machines.reasonCodes',
                'plants.shifts',
                'products',
                'reasonCodes.downtimeType'
            ]);
            $plants = $organization->plants;
            $products = $organization->products;
            $reasonCodes = $organization->reasonCodes;
            $downtimeTypes = \App\Models\DowntimeType::where('organization_id', $organization->id)->active()->ordered()->get();
            $lossTypes = \App\Models\LossType::where('organization_id', $organization->id)->active()->ordered()->get();
            $materialLossCategories = \App\Models\MaterialLossCategory::active()->with('lossType')->get();
            
            $organizationsPayload = collect([$organization]);
        }

        $managedPlantIds = $request->user()->isAdmin() 
            ? $plants->pluck('id')->values()->toArray()
            : $organization->plants->filter(fn($plant) => $request->user()->canManagePlant($plant->id))->pluck('id')->values()->toArray();

        // Fetch all production targets
        $targets = ProductionTarget::with(['machine.line.plant', 'line.plant', 'shift'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($target) {
                // Determine context name
                $plantName = $target->machine ? $target->machine->line->plant->name : ($target->line ? $target->line->plant->name : 'N/A');
                $lineName = $target->machine ? $target->machine->line->name : ($target->line ? $target->line->name : 'N/A');
                $entityName = $target->machine ? $target->machine->name : ($target->line ? 'Entire Line' : 'Unknown');

                return [
                    'id' => $target->id,
                    'machine_id' => $target->machine_id,
                    'line_id' => $target->line_id,
                    'machine_name' => $entityName,
                    'plant_name' => $plantName,
                    'line_name' => $lineName,
                    'shift_id' => $target->shift_id,
                    'shift_name' => $target->shift?->name ?? 'All Shifts',
                    'effective_from' => $target->effective_from,
                    'effective_to' => $target->effective_to,
                    'target_oee' => $target->target_oee,
                    'target_availability' => $target->target_availability,
                    'target_performance' => $target->target_performance,
                    'target_quality' => $target->target_quality,
                    'target_units' => $target->target_units,
                    'target_good_units' => $target->target_good_units,
                    'notes' => $target->notes,
                    'is_active' => $target->isActive(),
                ];
            });

        // Get all shifts available
        $shifts = collect();
        foreach ($plants as $plant) {
             if ($plant->shifts) {
                 $shifts = $shifts->merge($plant->shifts);
             }
        }
        $uniqueShifts = $shifts->unique('id')->values();

        return Inertia::render('Admin/Configuration/Index', [
            'organization' => $organization, // KEEP for backward compat in other tabs mostly
            'organizations' => $organizationsPayload, // NEW: Full list for tree
            'plants' => $plants,
            'products' => $products,
            'reasonCodes' => $reasonCodes,
            'materialLossCategories' => $materialLossCategories,
            'downtimeTypes' => $downtimeTypes,
            'lossTypes' => $lossTypes,
            'shifts' => $uniqueShifts,
            'targets' => $targets,
            'userPermissions' => [
                'managedPlantIds' => $managedPlantIds
            ]
        ]);
    }
}
