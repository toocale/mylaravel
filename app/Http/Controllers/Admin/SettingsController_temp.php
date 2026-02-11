<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request )
    {
        \ = \->user()->organization ?? Organization::first();

        if (!\) {
            return redirect()->route('dashboard')->with('error', 'No organization found.');
        }

        \->load([
            'reasonCodes',
            'plants.shifts' // Load shifts for all plants to manage them
        ]);

        return Inertia::render('Admin/Settings/Index', [
            'organization' => \,
            'reasonCodes' => \->reasonCodes,
            'plants' => \->plants
        ]);
    }
}
