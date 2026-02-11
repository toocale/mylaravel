<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    /**
     * Display the settings page.
     */
    public function index()
    {
        $settings = \App\Models\SiteSetting::all()->groupBy('group');
        return \Inertia\Inertia::render('Admin/Settings/Index', [
            'settings' => $settings
        ]);
    }

    /**
     * Update settings.
     */
    public function update(Request $request)
    {
        // Iterate over all inputs except standard Laravel ones
        foreach ($request->except(['_token', '_method']) as $key => $value) {
            // Find the setting first to ensure validity
            $setting = \App\Models\SiteSetting::where('key', $key)->first();
            
            if ($setting) {
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                    $path = $file->store('settings', 'public');
                    $value = '/storage/' . $path;
                }
                
                $setting->update(['value' => $value]);
            }
        }
        
        \Illuminate\Support\Facades\Cache::forget('site_settings');

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}
