<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Fingerprint, Trash2, Edit2, Plus } from 'lucide-vue-next';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { type BreadcrumbItem } from '@/types';
import { useWebAuthn } from '@/composables/useWebAuthn';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

interface Passkey {
    id: string;
    name: string;
    created_at: string;
    last_used_at: string;
}

const props = defineProps<{
    passkeys: Passkey[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Passkey settings',
        href: '/settings/passkeys',
    },
];

const { isSupported, isSecureContext, isProcessing, error: webauthnError, register } = useWebAuthn();

// State for adding new passkey
const showAddDialog = ref(false);
const newPasskeyName = ref('');
const isAddingPasskey = ref(false);

// State for editing passkey
const showEditDialog = ref(false);
const editingPasskey = ref<Passkey | null>(null);
const editForm = useForm({
    name: '',
});

// State for deleting passkey
const showDeleteDialog = ref(false);
const deletingPasskey = ref<Passkey | null>(null);

const handleAddPasskey = async () => {
    if (!newPasskeyName.value.trim()) {
        return;
    }

    isAddingPasskey.value = true;
    const success = await register({ name: newPasskeyName.value });
    
    if (success) {
        showAddDialog.value = false;
        newPasskeyName.value = '';
        router.reload({ only: ['passkeys'] });
    }
    
    isAddingPasskey.value = false;
};

const openEditDialog = (passkey: Passkey) => {
    editingPasskey.value = passkey;
    editForm.name = passkey.name;
    showEditDialog.value = true;
};

const handleEditPasskey = () => {
    if (!editingPasskey.value) return;

    editForm.patch(`/settings/passkeys/${editingPasskey.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
            editingPasskey.value = null;
        },
    });
};

const openDeleteDialog = (passkey: Passkey) => {
    deletingPasskey.value = passkey;
    showDeleteDialog.value = true;
};

const handleDeletePasskey = () => {
    if (!deletingPasskey.value) return;

    router.delete(`/settings/passkeys/${deletingPasskey.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDialog.value = false;
            deletingPasskey.value = null;
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Passkey settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Passkey settings"
                    description="Manage your passkeys for secure, passwordless authentication"
                />

                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Your Passkeys</CardTitle>
                                <CardDescription>
                                    Passkeys provide a secure and convenient way to sign in without a password
                                </CardDescription>
                            </div>
                            <Button
                                v-if="isSupported"
                                @click="showAddDialog = true"
                                class="gap-2"
                            >
                                <Plus :size="16" />
                                Add Passkey
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="!isSupported" class="text-center py-8 text-muted-foreground">
                            <div v-if="!isSecureContext">
                                <p class="font-medium text-destructive">Secure connection required</p>
                                <p class="text-sm mt-2">Passkeys require HTTPS or localhost. If you are using a local .test domain, please enable HTTPS.</p>
                            </div>
                            <div v-else>
                                <p>Your browser doesn't support passkeys.</p>
                                <p class="text-sm mt-2">Please use a modern browser like Chrome, Safari, or Edge.</p>
                            </div>
                        </div>

                        <div v-else-if="passkeys.length === 0" class="text-center py-8 text-muted-foreground">
                            <Fingerprint :size="48" class="mx-auto mb-4 opacity-50" />
                            <p>You don't have any passkeys yet.</p>
                            <p class="text-sm mt-2">Add a passkey to enable secure, passwordless sign-in.</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="passkey in passkeys"
                                :key="passkey.id"
                                class="flex items-center justify-between p-4 border rounded-lg hover:bg-accent/50 transition-colors"
                            >
                                <div class="flex items-center gap-4">
                                    <div class="p-2 bg-primary/10 rounded-lg">
                                        <Fingerprint :size="24" class="text-primary" />
                                    </div>
                                    <div>
                                        <p class="font-medium">{{ passkey.name }}</p>
                                        <p class="text-sm text-muted-foreground">
                                            Created {{ passkey.created_at }} • Last used {{ passkey.last_used_at }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        @click="openEditDialog(passkey)"
                                        variant="ghost"
                                        size="icon"
                                    >
                                        <Edit2 :size="16" />
                                    </Button>
                                    <Button
                                        @click="openDeleteDialog(passkey)"
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                    >
                                        <Trash2 :size="16" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Info Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>What are passkeys?</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm text-muted-foreground">
                        <p>
                            Passkeys are a secure and convenient way to sign in without passwords. They use your device's built-in security features like fingerprint, face recognition, or PIN.
                        </p>
                        <ul class="list-disc list-inside space-y-2 ml-2">
                            <li>More secure than passwords - resistant to phishing and data breaches</li>
                            <li>Faster sign-in - no need to remember or type passwords</li>
                            <li>Works across your devices when synced with your account</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>

        <!-- Add Passkey Dialog -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a new passkey</DialogTitle>
                    <DialogDescription>
                        Give your passkey a name to help you identify it later.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="passkey-name">Passkey name</Label>
                        <Input
                            id="passkey-name"
                            v-model="newPasskeyName"
                            placeholder="e.g., My Laptop, iPhone"
                            @keyup.enter="handleAddPasskey"
                        />
                    </div>
                    <div v-if="webauthnError" class="text-sm text-destructive">
                        {{ webauthnError }}
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        @click="showAddDialog = false"
                        variant="outline"
                        :disabled="isAddingPasskey || isProcessing"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleAddPasskey"
                        :disabled="!newPasskeyName.trim() || isAddingPasskey || isProcessing"
                        class="gap-2"
                    >
                        <Spinner v-if="isAddingPasskey || isProcessing" />
                        Add Passkey
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Passkey Dialog -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit passkey</DialogTitle>
                    <DialogDescription>
                        Update the name of your passkey.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="edit-passkey-name">Passkey name</Label>
                        <Input
                            id="edit-passkey-name"
                            v-model="editForm.name"
                            @keyup.enter="handleEditPasskey"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        @click="showEditDialog = false"
                        variant="outline"
                        :disabled="editForm.processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleEditPasskey"
                        :disabled="!editForm.name.trim() || editForm.processing"
                        class="gap-2"
                    >
                        <Spinner v-if="editForm.processing" />
                        Save Changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Passkey Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Are you sure?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete the passkey "{{ deletingPasskey?.name }}".
                        You won't be able to use it to sign in anymore.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        @click="showDeleteDialog = false"
                        variant="outline"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleDeletePasskey"
                        variant="destructive"
                    >
                        Delete Passkey
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
