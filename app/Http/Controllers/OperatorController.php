<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Organization;

class OperatorController extends Controller
{
    public function index(Request $request)
    {
        $organization = $request->user()->organization;

        if (!$organization) {
             // Basic fallback purely to prevent crash if not set up
             $organization = Organization::first();
        }

        if (!$organization) {
             return redirect()->route('dashboard')->with('error', 'No organization configured.');
        }

        // Load hierarchy for navigation
        $organization->load([
            'plants.lines.machines',
            'products',
            'reasonCodes' // Optional, better to load at machine level or distinct
        ]);

        // Get all shifts available in the organization's plants
        $shifts = collect();
        if ($organization->plants) {
            foreach ($organization->plants as $plant) {
                 if ($plant->shifts) {
                     $shifts = $shifts->merge($plant->shifts);
                 }
            }
        }
        // Also fetch any global shifts if applicable, or logic similar to ConfigurationController

        // Get users for operator selection (from same organization)
        $users = \App\Models\User::where('organization_id', $organization->id)
            ->with('groups')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'groups' => $u->groups->pluck('name')->toArray(),
            ]);

        // Check Kiosk Access
        if (!$request->user()->hasPermission('kiosk.view')) {
             abort(403, 'Unauthorized access to Operator Kiosk.');
        }

        // Get user permissions
        $permissions = $request->user()->groups->flatMap->permissions->pluck('name')->unique()->values();

        // Get assigned plants (if empty and not admin, user has no access?)
        // Admin gets all plants implicitly in backend check, but here we can pass all or nothing?
        // Actually, for UI logic, we pass assigned IDs.
        $assignedPlants = $request->user()->plants->pluck('id');

        return Inertia::render('Operator/Index', [
            'plants' => $organization->plants,
            'products' => $organization->products,
            'reasonCodes' => $organization->reasonCodes,
            'shifts' => \App\Models\Shift::all(),
            'users' => $users,
            'currentUser' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'permissions' => $permissions,
                'assigned_plants' => $assignedPlants,
                'is_admin' => $request->user()->isAdmin(),
            ],
        ]);
    }
}
