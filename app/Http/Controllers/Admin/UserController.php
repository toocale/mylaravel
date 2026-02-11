<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Check if user is admin or has users.view permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('users.view')) {
            abort(403, 'Unauthorized access.');
        }
        
        $query = User::query()->with(['groups', 'plants', 'organization']); // Eager load groups, plants, and organization

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('group')) {
            $query->whereHas('groups', function ($q) use ($request) {
                $q->where('groups.id', $request->group);
            });
        }

        $users = $query->latest()
            ->paginate(10)
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role, // Keep for backward compat
                    'organization_id' => $user->organization_id,
                    'organization_name' => $user->organization?->name ?? 'No Organization',
                    'groups' => $user->groups->pluck('id'), // Pass Group IDs
                    'group_names' => $user->groups->pluck('name'), // Pass Names for display
                    'plants' => $user->plants->pluck('id'), // Pass Plant IDs
                    'plant_names' => $user->plants->pluck('name'), // Pass Plant Names
                    'is_active' => $user->is_active,
                    'is_online' => \Illuminate\Support\Facades\Cache::has('user-is-online-' . $user->id),
                    'last_login_at' => $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never',
                    'created_at' => $user->created_at->format('M d, Y'),
                ];
            });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'group']),
            'available_groups' => \App\Models\Group::all(['id', 'name']),
            'available_plants' => \App\Models\Plant::all(['id', 'name']),
            'available_organizations' => \App\Models\Organization::all(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Check if user is admin or has users.create permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('users.create')) {
            abort(403, 'Unauthorized access.');
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string', // Keep legacy or make optional
            'organization_id' => 'nullable|exists:organizations,id',
            'groups' => 'nullable|array',
            'groups.*' => 'exists:groups,id',
            'plants' => 'nullable|array',
            'plants.*' => 'exists:plants,id',
            'is_active' => 'boolean',
        ]);

        // Auto-assign organization if not provided
        $organizationId = $validated['organization_id'] ?? $request->user()->organization_id;
        
        // If still no organization, assign to first real organization
        if (!$organizationId) {
            $org = \App\Models\Organization::where('name', '!=', 'Default Org')->orderBy('created_at')->first();
            $organizationId = $org?->id ?? \App\Models\Organization::first()?->id;
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => $validated['role'],
            'organization_id' => $organizationId,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Sync groups and plants
        $user->groups()->sync($validated['groups'] ?? []);
        $user->plants()->sync($validated['plants'] ?? []);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        // Check if user is admin or has users.edit permission
        if (!$request->user()->isAdmin() && !$request->user()->hasPermission('users.edit')) {
            abort(403, 'Unauthorized access.');
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|string',
            'organization_id' => 'nullable|exists:organizations,id',
            'groups' => 'nullable|array',
            'groups.*' => 'exists:groups,id',
            'plants' => 'nullable|array',
            'plants.*' => 'exists:plants,id',
            'is_active' => 'boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'organization_id' => $validated['organization_id'] ?? $user->organization_id,
            'is_active' => $validated['is_active'],
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => \Illuminate\Support\Facades\Hash::make($request->password)]);
        }

        // Sync groups and plants
        $user->groups()->sync($validated['groups'] ?? []);
        $user->plants()->sync($validated['plants'] ?? []);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Check if user is admin or has users.delete permission
        if (!request()->user()->isAdmin() && !request()->user()->hasPermission('users.delete')) {
            abort(403, 'Unauthorized access.');
        }
        
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}
