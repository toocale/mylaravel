<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasskeyController extends Controller
{
    /**
     * Display the passkey management page.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        
        // Get all passkeys for the user
        $passkeys = $user->webAuthnCredentials()
            ->get()
            ->map(function ($credential) {
                return [
                    'id' => $credential->id,
                    'name' => $credential->name ?? 'Unnamed Passkey',
                    'created_at' => $credential->created_at->format('M d, Y'),
                    'last_used_at' => $credential->updated_at->format('M d, Y'),
                ];
            });

        return Inertia::render('settings/Passkeys', [
            'passkeys' => $passkeys,
        ]);
    }

    /**
     * Update a passkey's name.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $credential = $user->webAuthnCredentials()->findOrFail($id);
        
        $credential->update([
            'name' => $request->name,
        ]);

        return back()->with('status', 'Passkey updated successfully.');
    }

    /**
     * Delete a passkey.
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        
        // Ensure user has at least one other authentication method
        $passkeyCount = $user->webAuthnCredentials()->count();
        $hasPassword = !empty($user->password);
        
        if ($passkeyCount === 1 && !$hasPassword) {
            return back()->withErrors([
                'passkey' => 'You must have at least one authentication method. Please set a password before removing your last passkey.',
            ]);
        }

        $credential = $user->webAuthnCredentials()->findOrFail($id);
        $credential->delete();

        return back()->with('status', 'Passkey removed successfully.');
    }
}
