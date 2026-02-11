import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface PublicKeyCredentialCreationOptionsJSON {
    challenge: string;
    rp: {
        name: string;
        id?: string;
    };
    user: {
        id: string;
        name: string;
        displayName: string;
    };
    pubKeyCredParams: Array<{
        type: string;
        alg: number;
    }>;
    timeout?: number;
    excludeCredentials?: Array<{
        id: string;
        type: string;
        transports?: string[];
    }>;
    authenticatorSelection?: {
        authenticatorAttachment?: string;
        requireResidentKey?: boolean;
        residentKey?: string;
        userVerification?: string;
    };
    attestation?: string;
}

interface PublicKeyCredentialRequestOptionsJSON {
    challenge: string;
    timeout?: number;
    rpId?: string;
    allowCredentials?: Array<{
        id: string;
        type: string;
        transports?: string[];
    }>;
    userVerification?: string;
}

export function useWebAuthn() {
    const isSecureContext = typeof window !== 'undefined' && window.isSecureContext;

    const isSupported = ref(
        typeof window !== 'undefined' &&
        typeof window.PublicKeyCredential !== 'undefined' &&
        isSecureContext
    );
    const isProcessing = ref(false);
    const error = ref<string | null>(null);

    if (typeof window !== 'undefined' && !isSecureContext) {
        console.warn('WebAuthn requires a secure context (HTTPS or localhost).');
    }

    // Helper function to convert base64url to ArrayBuffer
    const base64urlToBuffer = (base64url: string): ArrayBuffer => {
        const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
        const padLen = (4 - (base64.length % 4)) % 4;
        const padded = base64 + '='.repeat(padLen);
        const binary = atob(padded);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    };

    // Helper function to convert ArrayBuffer to base64url
    const bufferToBase64url = (buffer: ArrayBuffer): string => {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=/g, '');
    };

    // Get CSRF token
    const getCsrfToken = (): string => {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? (token as HTMLMetaElement).content : '';
    };

    // Register a new passkey
    const register = async (data: Record<string, any> = {}) => {
        if (!isSupported.value) {
            error.value = 'WebAuthn is not supported in this browser';
            return false;
        }

        isProcessing.value = true;
        error.value = null;

        try {
            // Get registration options from server
            const optionsResponse = await fetch('/webauthn/register/options', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            });

            if (!optionsResponse.ok) {
                let errorMessage = 'Failed to get registration options';
                try {
                    const errorData = await optionsResponse.json();
                    if (errorData.message) errorMessage = errorData.message;
                } catch (e) {
                    // Ignore JSON parse error
                }
                throw new Error(errorMessage);
            }

            const options: PublicKeyCredentialCreationOptionsJSON = await optionsResponse.json();

            // Convert base64url strings to ArrayBuffers
            const publicKeyOptions: PublicKeyCredentialCreationOptions = {
                challenge: base64urlToBuffer(options.challenge),
                rp: options.rp,
                user: {
                    ...options.user,
                    id: base64urlToBuffer(options.user.id),
                },
                pubKeyCredParams: options.pubKeyCredParams.map(param => ({
                    type: param.type as PublicKeyCredentialType,
                    alg: param.alg,
                })),
                timeout: options.timeout,
                excludeCredentials: options.excludeCredentials?.map(cred => ({
                    id: base64urlToBuffer(cred.id),
                    type: cred.type as PublicKeyCredentialType,
                    transports: cred.transports as AuthenticatorTransport[] | undefined,
                })),
                authenticatorSelection: options.authenticatorSelection as AuthenticatorSelectionCriteria | undefined,
                attestation: options.attestation as AttestationConveyancePreference | undefined,
            };

            // Create credential
            const credential = await navigator.credentials.create({
                publicKey: publicKeyOptions,
            }) as PublicKeyCredential;

            if (!credential) {
                throw new Error('Failed to create credential');
            }

            const response = credential.response as AuthenticatorAttestationResponse;

            // Prepare credential data for server
            const credentialData = {
                id: credential.id,
                rawId: bufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: bufferToBase64url(response.clientDataJSON),
                    attestationObject: bufferToBase64url(response.attestationObject),
                },
                ...data,
            };

            // Send credential to server
            const registerResponse = await fetch('/webauthn/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(credentialData),
            });

            if (!registerResponse.ok) {
                const errorData = await registerResponse.json();
                throw new Error(errorData.message || 'Failed to register passkey');
            }

            return true;
        } catch (err: any) {
            error.value = err.message || 'Failed to register passkey';
            console.error('WebAuthn registration error:', err);
            return false;
        } finally {
            isProcessing.value = false;
        }
    };

    // Login with passkey
    const login = async (email?: string) => {
        if (!isSupported.value) {
            error.value = 'WebAuthn is not supported in this browser';
            return false;
        }

        isProcessing.value = true;
        error.value = null;

        try {
            // Get login options from server
            const optionsResponse = await fetch('/webauthn/login/options', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(email ? { email } : {}),
            });

            if (!optionsResponse.ok) {
                let errorMessage = 'Failed to get login options';
                try {
                    const errorData = await optionsResponse.json();
                    if (errorData.message) errorMessage = errorData.message;
                } catch (e) {
                    // Ignore JSON parse error, use default message
                }
                throw new Error(errorMessage);
            }

            const options: PublicKeyCredentialRequestOptionsJSON = await optionsResponse.json();

            // Convert base64url strings to ArrayBuffers
            const publicKeyOptions: PublicKeyCredentialRequestOptions = {
                challenge: base64urlToBuffer(options.challenge),
                timeout: options.timeout,
                rpId: options.rpId,
                allowCredentials: options.allowCredentials?.map(cred => ({
                    id: base64urlToBuffer(cred.id),
                    type: cred.type as PublicKeyCredentialType,
                    transports: cred.transports as AuthenticatorTransport[] | undefined,
                })),
                userVerification: options.userVerification as UserVerificationRequirement | undefined,
            };

            // Get credential
            const credential = await navigator.credentials.get({
                publicKey: publicKeyOptions,
            }) as PublicKeyCredential;

            if (!credential) {
                throw new Error('Failed to get credential');
            }

            const response = credential.response as AuthenticatorAssertionResponse;

            // Prepare credential data for server
            const credentialData = {
                id: credential.id,
                rawId: bufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: bufferToBase64url(response.clientDataJSON),
                    authenticatorData: bufferToBase64url(response.authenticatorData),
                    signature: bufferToBase64url(response.signature),
                    userHandle: response.userHandle ? bufferToBase64url(response.userHandle) : null,
                },
            };

            // Send credential to server
            const loginResponse = await fetch('/webauthn/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(credentialData),
            });

            if (!loginResponse.ok) {
                const errorData = await loginResponse.json();
                throw new Error(errorData.message || 'Failed to login with passkey');
            }

            // Redirect to dashboard on success
            router.visit('/dashboard');
            return true;
        } catch (err: any) {
            error.value = err.message || 'Failed to login with passkey';
            console.error('WebAuthn login error:', err);
            return false;
        } finally {
            isProcessing.value = false;
        }
    };

    return {
        isSupported,
        isSecureContext,
        isProcessing,
        error,
        register,
        login,
    };
}
