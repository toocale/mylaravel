<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { MoreHorizontal, UserPlus, Shield, Circle, Search, Pencil, Trash2 } from 'lucide-vue-next';
import { useDebounceFn } from '@vueuse/core';
import { useToast } from '@/components/ui/toast/use-toast';
import { computed } from 'vue';
import { useTerminology } from '@/composables/useTerminology';

const props = defineProps<{
    users: {
        data: Array<{
            id: number;
            name: string;
            email: string;
            role: string;
            groups: number[];
            group_names: string[];
            is_active: boolean;
            is_online: boolean;
            last_login_at: string;
            created_at: string;
        }>;
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    filters: {
        search: string;
        group?: string;
    };
    available_groups: Array<{ id: number; name: string }>;
    available_plants: Array<{ id: number; name: string }>;
}>();

// Search with Debounce
const search = ref(props.filters.search);
const debouncedSearch = useDebounceFn((value: string) => {
    router.get('/admin/users', { search: value }, { preserveState: true, replace: true });
}, 300);

watch(search, (newVal) => {
    debouncedSearch(newVal);
});

// Group Filter
const selectedGroupFilter = ref(props.filters.group || '');

watch(selectedGroupFilter, (newVal) => {
    router.get('/admin/users', { 
        search: search.value, 
        group: newVal 
    }, { 
        preserveState: true, 
        replace: true 
    });
});

// Permissions
const page = usePage();
const permissions = computed(() => (page.props.auth as any).permissions || []);
const isAdmin = computed(() => (page.props.auth as any).user?.role === 'admin');
const canDeleteUsers = computed(() => isAdmin.value || permissions.value.includes('users.delete'));
const canEditUsers = computed(() => isAdmin.value || permissions.value.includes('users.edit'));
const { toast } = useToast();
const { plants: plantsTerm, plant: plantTerm } = useTerminology();

// Add/Edit User Form
const isDialogOpen = ref(false);
const editingUser = ref<any>(null);

// Use a separate ref for groups to avoid Inertia form reactivity issues
const selectedGroups = ref<number[]>([]);
const selectedPlants = ref<number[]>([]);

const form = useForm({
    name: '',
    email: '',
    role: 'member',
    password: '',
    is_active: true,
});



const openAddDialog = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    selectedGroups.value = [];
    selectedPlants.value = [];
    isDialogOpen.value = true;
};

// Toggle group selection
const toggleGroup = (grpId: number, checked: boolean) => {
    if (checked) {
        if (!selectedGroups.value.includes(grpId)) {
            selectedGroups.value = [...selectedGroups.value, grpId];
        }
    } else {
        selectedGroups.value = selectedGroups.value.filter(id => id !== grpId);
    }
};

const togglePlant = (plantId: number, checked: boolean) => {
    if (checked) {
        if (!selectedPlants.value.includes(plantId)) {
            selectedPlants.value = [...selectedPlants.value, plantId];
        }
    } else {
        selectedPlants.value = selectedPlants.value.filter(id => id !== plantId);
    }
};

const openEditDialog = (user: any) => {
    console.log('openEditDialog', user);
    editingUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    selectedGroups.value = (user.groups || []).map((id: any) => Number(id));
    selectedPlants.value = (user.plants || []).map((id: any) => Number(id));
    console.log('populated selectedGroups', selectedGroups.value);
    form.is_active = !!user.is_active;
    form.password = '';
    form.clearErrors();
    isDialogOpen.value = true;
};

const submitForm = () => {
    const groupsToSend = [...selectedGroups.value];
    const plantsToSend = [...selectedPlants.value];
    console.log('submitForm called', { editingUser: editingUser.value, groupsToSend, plantsToSend });

    const options = {
        onStart: () => console.log('request started'),
        onSuccess: (page: any) => {
            console.log('request succeeded', page);
            isDialogOpen.value = false;
            form.reset();
            selectedGroups.value = [];
            selectedPlants.value = [];
        },
        onError: (errors: any) => {
            console.log('request errors', errors);
        },
        onFinish: () => console.log('request finished'),
    };

    if (editingUser.value) {
        router.put(`/admin/users/${editingUser.value.id}`, {
            name: form.name,
            email: form.email,
            role: form.role,
            groups: groupsToSend,
            plants: plantsToSend,
            password: form.password,
            is_active: form.is_active,
        }, {
            ...options,
            preserveScroll: true,
        });
    } else {
        router.post('/admin/users', {
            name: form.name,
            email: form.email,
            role: form.role,
            groups: groupsToSend,
            plants: plantsToSend,
            password: form.password,
            is_active: form.is_active,
        }, {
            ...options,
            preserveScroll: true,
        });
    }
};

// Delete User
const userToDelete = ref<any>(null);
const isDeleteDialogOpen = ref(false);

const confirmDelete = (user: any) => {
    userToDelete.value = user;
    isDeleteDialogOpen.value = true;
};

const deleteUser = () => {
    if (userToDelete.value) {
        router.delete(`/admin/users/${userToDelete.value.id}`, {
            onSuccess: () => {
                isDeleteDialogOpen.value = false;
                userToDelete.value = null;
                toast({
                    title: 'User deleted',
                    description: 'User has been successfully deleted.',
                });
            },
            onError: (errors) => {
                isDeleteDialogOpen.value = false;
                userToDelete.value = null;
                
                // Show friendly error message
                toast({
                    title: 'Permission Denied',
                    description: 'You do not have permission to delete users.',
                    variant: 'destructive',
                });
            },
        });
    }
};

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'User Management', href: '/admin/users' },
];
</script>

<template>
    <Head title="User Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 space-y-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight">User Management</h2>
                    <p class="text-muted-foreground">Manage system access, roles, and member status.</p>
                </div>
                <Button @click="openAddDialog">
                    <UserPlus class="mr-2 h-4 w-4" /> Add Member
                </Button>
            </div>

            <div class="flex items-center space-x-2">
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Search users..."
                        class="pl-8"
                    />
                </div>
                <div class="w-[200px]">
                    <select
                        v-model="selectedGroupFilter"
                        class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">All Groups</option>
                        <option v-for="grp in available_groups" :key="grp.id" :value="grp.id">
                            {{ grp.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Custom Table Implementation -->
            <div class="rounded-md border bg-card overflow-hidden">
                <div class="w-full overflow-x-auto">
                    <table class="w-full caption-bottom text-sm text-left">
                        <thead class="[&_tr]:border-b">
                            <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground">User</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground hidden md:table-cell">Account Type</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Groups</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground">Status</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground hidden lg:table-cell">Online Status</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground hidden lg:table-cell">Last Login</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground hidden xl:table-cell">Joined</th>
                                <th class="h-12 px-4 align-middle font-medium text-muted-foreground text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr:last-child]:border-0">
                            <tr v-for="user in users.data" :key="user.id" class="border-b transition-colors hover:bg-muted/50">
                                <td class="p-4 align-middle">
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ user.name }}</span>
                                        <span class="text-xs text-muted-foreground">{{ user.email }}</span>
                                    </div>
                                </td>
                                <!-- Account Type Column -->
                                <td class="p-4 align-middle hidden md:table-cell">
                                    <Badge variant="outline" class="text-xs border-primary/30 text-primary uppercase text-[10px] tracking-wider font-bold">
                                        {{ user.role === 'admin' ? 'System User' : 'Member' }}
                                    </Badge>
                                </td>
                                
                                <!-- Groups Column -->
                                <td class="p-4 align-middle">
                                    <div class="flex flex-wrap gap-1">
                                        <Badge v-for="grp in user.group_names" :key="grp" variant="secondary" class="text-xs">
                                            {{ grp }}
                                        </Badge>
                                        <span v-if="!user.group_names || user.group_names.length === 0" class="text-xs text-muted-foreground">-</span>
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <Badge :variant="user.is_active ? 'outline' : 'destructive'" class="font-mono text-[10px] uppercase">
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </td>
                                <td class="p-4 align-middle hidden lg:table-cell">
                                    <div v-if="user.is_online" class="flex items-center text-green-600 text-xs font-medium">
                                        <span class="relative flex h-2 w-2 mr-2">
                                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                          <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                        </span>
                                        Online
                                    </div>
                                    <div v-else class="flex items-center text-muted-foreground text-xs">
                                        <Circle class="h-2 w-2 mr-2 fill-current opacity-30" />
                                        Offline
                                    </div>
                                </td>
                                <td class="p-4 align-middle text-muted-foreground text-xs hidden lg:table-cell">
                                    {{ user.last_login_at }}
                                </td>
                                <td class="p-4 align-middle text-muted-foreground text-xs hidden xl:table-cell">
                                    {{ user.created_at }}
                                </td>
                                <td class="p-4 align-middle text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 p-0">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem v-if="canEditUsers" @click="openEditDialog(user)">
                                                <Pencil class="mr-2 h-4 w-4" /> Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="canDeleteUsers" @click="confirmDelete(user)" class="text-red-600">
                                                <Trash2 class="mr-2 h-4 w-4" /> Delete
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="!canEditUsers && !canDeleteUsers" disabled>
                                                <span class="text-muted-foreground text-xs">No actions available</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between" v-if="users.links.length > 3">
                <div class="text-sm text-muted-foreground">
                    Showing {{ users.from }} to {{ users.to }} of {{ users.total }} users
                </div>
                <div class="flex gap-2">
                    <Button
                        v-for="(link, i) in users.links"
                        :key="i"
                        :variant="link.active ? 'default' : 'outline'"
                        :disabled="!link.url"
                        as-child
                        size="sm"
                    >
                        <Link v-if="link.url" :href="link.url" v-html="link.label" />
                        <span v-else v-html="link.label"></span>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Add/Edit Dialog -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingUser ? 'Edit User' : 'Add New Member' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingUser ? 'Update user details and permissions.' : 'Create a new user account with specific roles.' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="name">Full Name</Label>
                        <Input id="name" v-model="form.name" placeholder="John Doe" required />
                        <span v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</span>
                    </div>

                    <div class="space-y-2">
                        <Label for="email">Email Address</Label>
                        <Input id="email" type="email" v-model="form.email" placeholder="john@example.com" required />
                        <span v-if="form.errors.email" class="text-xs text-red-500">{{ form.errors.email }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Legacy Role (kept hidden or minimized?) -->
                        <div class="space-y-2">
                            <Label for="role">Primary Role (Legacy)</Label>
                            <select 
                                v-model="form.role" 
                                class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="member">Member</option>
                                <option value="admin">Administrator (Super)</option>
                            </select>
                        </div>

                        <div class="space-y-2" v-if="editingUser">
                            <Label>Account Status</Label>
                            <div class="flex items-center space-x-2 h-10">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.is_active"
                                    @click="form.is_active = !form.is_active"
                                    :class="[
                                        form.is_active ? 'bg-primary' : 'bg-input',
                                        'peer inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50'
                                    ]"
                                >
                                    <span
                                        :class="[
                                            form.is_active ? 'translate-x-5' : 'translate-x-0',
                                            'pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform'
                                        ]"
                                    />
                                </button>
                                <span class="text-sm font-medium">{{ form.is_active ? 'Active' : 'Suspended' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>User Groups (Access Levels)</Label>
                        <div class="flex flex-wrap gap-4 border p-3 rounded-md min-h-[80px]">
                            <div v-if="available_groups.length === 0" class="text-muted-foreground text-sm">No groups available.</div>
                            <div v-for="grp in available_groups" :key="grp.id" class="flex items-center space-x-2">
                                <input 
                                    type="checkbox"
                                    :id="'grp-'+grp.id"
                                    :checked="selectedGroups.includes(Number(grp.id))"
                                    @change="(e: Event) => toggleGroup(Number(grp.id), (e.target as HTMLInputElement).checked)"
                                    class="h-4 w-4 rounded border-gray-300"
                                />
                                <label :for="'grp-'+grp.id" class="text-sm font-medium leading-none cursor-pointer">
                                    {{ grp.name }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Assigned {{ plantsTerm }} (Operational Access)</Label>
                        
                        <div v-if="form.role === 'admin'" class="p-3 bg-blue-50 text-blue-700 text-xs rounded-md border border-blue-200 mb-2 flex items-start gap-2">
                            <Shield class="h-4 w-4 mt-0.5 shrink-0" />
                            <div>
                                <span class="font-bold">Full Access Granted:</span> Administrators have management access to ALL {{ plantsTerm }} automatically. Specific {{ plantTerm.toLowerCase() }} assignments below are ignored for Admins.
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-4 border p-3 rounded-md min-h-[80px]" :class="{'opacity-50 pointer-events-none bg-muted': form.role === 'admin'}">
                            <div v-if="available_plants.length === 0" class="text-muted-foreground text-sm">No {{ plantsTerm }} available.</div>
                            <div v-for="plant in available_plants" :key="plant.id" class="flex items-center space-x-2">
                                <input 
                                    type="checkbox"
                                    :id="'plant-'+plant.id"
                                    :checked="selectedPlants.includes(Number(plant.id)) || form.role === 'admin'" 
                                    @change="(e: Event) => togglePlant(Number(plant.id), (e.target as HTMLInputElement).checked)"
                                    class="h-4 w-4 rounded border-gray-300"
                                    :disabled="form.role === 'admin'"
                                />
                                <label :for="'plant-'+plant.id" class="text-sm font-medium leading-none cursor-pointer">
                                    {{ plant.name }}
                                </label>
                            </div>
                        </div>
                        <p class="text-[10px] text-muted-foreground" v-if="form.role !== 'admin'">Select which {{ plantsTerm }} this user can manage (start shifts, log downtime, edit settings).</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="password">{{ editingUser ? 'New Password (Optional)' : 'Password' }}</Label>
                        <Input id="password" type="password" v-model="form.password" placeholder="••••••••" :required="!editingUser" />
                        <span v-if="form.errors.password" class="text-xs text-red-500">{{ form.errors.password }}</span>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="isDialogOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editingUser ? 'Save Changes' : 'Create Member' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete User?</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete <strong>{{ userToDelete?.name }}</strong>? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="deleteUser">Delete User</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
