<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;

use function response;

class WebAuthnLoginController
{
    /**
     * Returns the challenge to assertion.
     */
    public function options(AssertionRequest $request): Responsable|JsonResponse
    {
        try {
            return $request->toVerify($request->validate(['email' => 'sometimes|email|string']));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate login challenge: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log the user in.
     */
    public function login(AssertedRequest $request): Response|JsonResponse
    {
        try {
            $success = $request->login();
            
            if (!$success) {
                \Illuminate\Support\Facades\Log::error('WebAuthn login failed', [
                    'client_data' => $request->json('response.clientDataJSON'),
                    'credential_id' => $request->json('id'),
                ]);
                
                return response()->json([
                    'message' => 'Passkey validation failed. Ensure your device is registered and retry.',
                ], 422);
            }
            
            return response()->noContent();

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('WebAuthn login exception: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to login: ' . $e->getMessage()
            ], 500);
        }

    }
}
