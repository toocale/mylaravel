<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GroupController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Groups/Index', [
            'groups' => Group::with('permissions')->get(), // Fetch all groups
            'permissions' => Permission::all(), // For the form checkbox list
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|unique:groups,name',
            'description' => 'nullable|string',
            'permissions' => 'array', // Array of permission IDs
            'permissions.*' => 'exists:permissions,id',
        ]);

        $group = Group::create($request->only('name', 'description'));

        if ($request->has('permissions')) {
            $group->permissions()->sync($request->permissions);
        }

        return redirect()->back()->with('success', 'Group created.');
    }

    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => 'required|unique:groups,name,' . $group->id,
            'description' => 'nullable|string',
            'permissions' => 'array',
        ]);

        $group->update($request->only('name', 'description'));

        if ($request->has('permissions')) {
            $group->permissions()->sync($request->permissions);
        }

        return redirect()->back()->with('success', 'Group updated.');
    }

    public function destroy(Group $group)
    {
        if ($group->name === 'Admin') {
            return redirect()->back()->with('error', 'Cannot delete Admin group.');
        }
        $group->delete();
        return redirect()->back()->with('success', 'Group deleted.');
    }
}
