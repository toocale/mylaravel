<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

use function response;

class WebAuthnRegisterController
{
    /**
     * Returns a challenge to be verified by the user device.
     */
    public function options(AttestationRequest $request): Responsable|JsonResponse
    {
        try {
            return $request
                ->fastRegistration()
//            ->userless()
//            ->allowDuplicates()
                ->toCreate();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate registration challenge: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registers a device for further WebAuthn authentication.
     */
    public function register(AttestedRequest $request): Response|JsonResponse
    {
        try {
            $request->save();
            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to register passkey: ' . $e->getMessage()
            ], 500);
        }
    }
}
