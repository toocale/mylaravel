<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';
import login from '@/routes/login';
import { Head, useForm } from '@inertiajs/vue3';
import { useWebAuthn } from '@/composables/useWebAuthn';
import { Fingerprint } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const { isSupported, isProcessing: passkeyProcessing, error: passkeyError, login: passkeyLogin } = useWebAuthn();
const showPasswordForm = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(login.store().url, {
        onFinish: () => {
            form.reset('password');
        },
    });
};

const handlePasskeyLogin = async () => {
    const success = await passkeyLogin();
    if (!success) {
        console.error('Passkey login failed:', passkeyError.value);
    }
};
</script>

<template>
    <AuthBase
        title="Log in to your account"
        description="Enter your email and password below to log in"
    >
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <!-- Passkey Login Section -->
        <div v-if="isSupported && !showPasswordForm" class="flex flex-col gap-4">
            <Button
                @click="handlePasskeyLogin"
                type="button"
                class="w-full gap-2"
                size="lg"
                :disabled="passkeyProcessing"
            >
                <Spinner v-if="passkeyProcessing" />
                <Fingerprint v-else :size="20" />
                Sign in with Passkey
            </Button>

            <div v-if="passkeyError" class="text-sm text-destructive text-center">
                {{ passkeyError }}
            </div>

            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t" />
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-background px-2 text-muted-foreground">
                        Or continue with
                    </span>
                </div>
            </div>

            <Button
                @click="showPasswordForm = true"
                type="button"
                variant="outline"
                class="w-full"
            >
                Use password instead
            </Button>
        </div>

        <!-- Password Login Form -->
        <form
            v-if="!isSupported || showPasswordForm"
            @submit.prevent="submit"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        v-model="form.email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        v-model="form.password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" v-model:checked="form.remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="form.processing"
                    data-test="login-button"
                >
                    <Spinner v-if="form.processing" />
                    Log in
                </Button>

                <!-- Back to passkey button -->
                <Button
                    v-if="isSupported && showPasswordForm"
                    @click="showPasswordForm = false"
                    type="button"
                    variant="ghost"
                    class="w-full gap-2"
                >
                    <Fingerprint :size="16" />
                    Back to passkey login
                </Button>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
                Don't have an account?
                <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
