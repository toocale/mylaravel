<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = auth()->user();
        
        // Redirect Operator-only users to Kiosk
        // If user has 'kiosk.view' but NOT 'oee.view' (Dashboard access), send to Kiosk
        if ($user && $user->hasPermission('kiosk.view') && !$user->isAdmin() && !$user->hasPermission('oee.view')) {
            return redirect()->route('operator');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
