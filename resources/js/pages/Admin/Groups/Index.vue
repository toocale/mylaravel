<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Plus, Pencil, Trash2, Shield, ChevronDown, ChevronRight } from 'lucide-vue-next';

interface Permission {
    id: number;
    name: string;
    description: string | null;
}

interface Group {
    id: number;
    name: string;
    description: string | null;
    permissions: Permission[];
}

const props = defineProps<{
    groups: Group[];
    permissions: Permission[];
}>();

console.log('Admin/Groups/Index mounted', props);
if (!props.permissions) console.error('Permissions prop is missing!');

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'User Groups', href: '/admin/groups' },
];

// --- FORM ---
const isDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const groupToDelete = ref<number | null>(null);

const form = useForm({
    id: null as number | null,
    name: '',
    description: '',
    permissions: [] as number[],
});

const openDialog = (group: Group | null = null) => {
    form.reset();
    form.clearErrors();
    if (group) {
        form.id = group.id;
        form.name = group.name;
        form.description = group.description || '';
        form.permissions = group.permissions.map(p => p.id);
    }
    isDialogOpen.value = true;
};

const submitForm = () => {
    if (form.id) {
        form.put(`/admin/groups/${form.id}`, { onSuccess: () => isDialogOpen.value = false });
    } else {
        form.post('/admin/groups', { onSuccess: () => isDialogOpen.value = false });
    }
};

const deleteGroup = (id: number) => {
    groupToDelete.value = id;
    isDeleteDialogOpen.value = true;
};

const confirmDelete = () => {
    if (groupToDelete.value) {
        useForm({}).delete(`/admin/groups/${groupToDelete.value}`, {
            onSuccess: () => {
                isDeleteDialogOpen.value = false;
                groupToDelete.value = null;
            },
        });
    }
};

const hasPermission = (group: Group, permissionId: number) => {
    const result = group.permissions.some(p => Number(p.id) === Number(permissionId));
    // Debug: uncomment to see what's happening
    // console.log(`hasPermission: group=${group.name}, permId=${permissionId}, result=${result}, groupPerms=`, group.permissions.map(p => p.id));
    return result;
};

const updateGroupPermission = (group: Group, permissionId: number, checked: boolean) => {
    const currentPermissionIds = group.permissions.map(p => p.id);
    let newPermissionIds = [...currentPermissionIds];

    if (checked) {
        newPermissionIds.push(permissionId);
    } else {
        newPermissionIds = newPermissionIds.filter(id => id !== permissionId);
    }

    router.put(`/admin/groups/${group.id}`, {
        name: group.name,
        description: group.description,
        permissions: newPermissionIds,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['groups'],
    });
};

const getPermissionDisplayName = (permission: Permission): string => {
    const nameMap: Record<string, string> = {
        'users.view': 'View Users',
        'users.create': 'Create Users',
        'users.edit': 'Edit Users',
        'users.delete': 'Delete Users',
        'groups.manage': 'Manage User Groups',
        'oee.view': 'View OEE Dashboard',
        'oee.manage.config': 'Configure OEE Settings',
        'reports.view': 'View Reports',
    };
    
    return nameMap[permission.name] || permission.name;
};

const groupedPermissions = computed(() => {
    const groups: Record<string, Permission[]> = {};
    
    // Define category map
    const categoryMap: Record<string, string> = {
        'users': 'User Management',
        'groups': 'User Groups',
        'oee': 'OEE & Analytics',
        'reports': 'Reports',
        'assets': 'Assets & Infrastructure',
        'products': 'Product Management',
        'shifts': 'Shift Management',
        'targets': 'Production Targets',
        'maintenance': 'Maintenance & Health',
        'kiosk': 'Operator Kiosk',
        'shift': 'Shift Operations',
    };

    if (!props.permissions) return {};

    props.permissions.forEach(perm => {
        const prefix = perm.name.split('.')[0];
        const category = categoryMap[prefix] || 'Other';
        
        if (!groups[category]) {
            groups[category] = [];
        }
        groups[category].push(perm);
    });

    // Sort categories (optional, but nice)
    // We can rely on JS object insertion order or keep it simple. 
    // Let's sort keys to put 'Other' last if needed, but standard loop is usually fine.
    
    return groups;
});

// Collapsing Logic
const collapsedCategories = ref<Record<string, boolean>>({});

const toggleCategory = (category: string) => {
    collapsedCategories.value[category] = !collapsedCategories.value[category];
};

const isCategoryCollapsed = (category: string) => {
    return !!collapsedCategories.value[category];
};

</script>

<template>
    <Head title="User Groups" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative overflow-hidden rounded-3xl min-h-[calc(100vh-100px)] bg-background/50 border border-border/50 mx-4 my-4">
            <!-- Animated Background Mesh -->
            <div class="absolute inset-0 z-0 opacity-40 dark:opacity-20 pointer-events-none">
                <div class="absolute top-[-20%] left-[-10%] w-[60%] h-[60%] rounded-full bg-blue-500/30 blur-[120px] animate-blob"></div>
                <div class="absolute bottom-[-20%] right-[-10%] w-[60%] h-[60%] rounded-full bg-purple-500/30 blur-[120px] animate-blob animation-delay-2000"></div>
                <div class="absolute top-[30%] left-[30%] w-[50%] h-[50%] rounded-full bg-emerald-500/20 blur-[120px] animate-blob animation-delay-4000"></div>
            </div>

            <div class="relative z-10 p-4 md:p-6">
                <!-- Header -->
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-foreground to-muted-foreground bg-clip-text text-transparent">User Groups</h2>
                        <p class="text-muted-foreground mt-1">Manage roles and define access permissions across the platform.</p>
                    </div>
                    <Button @click="openDialog()" class="shadow-lg shadow-primary/20 transition-all hover:scale-105">
                        <Plus class="mr-2 h-4 w-4" /> Create Group
                    </Button>
                </div>

                <!-- Grid -->
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div 
                        v-for="group in groups" 
                        :key="group.id" 
                        class="glass-panel rounded-2xl p-6 border border-white/20 shadow-lg hover:shadow-xl transition-all duration-300 hover:translate-y-[-2px] flex flex-col justify-between min-h-[500px]"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="p-2 rounded-xl bg-primary/10 text-primary">
                                        <Shield class="h-5 w-5" />
                                    </div>
                                    <h3 class="font-semibold text-lg">{{ group.name }}</h3>
                                </div>
                                <div v-if="group.name === 'Admin'" class="px-2 py-1 rounded-md bg-amber-500/10 text-amber-600 text-[10px] font-bold tracking-wider uppercase border border-amber-500/20">
                                    System
                                </div>
                            </div>
                            
                            <p class="text-sm text-muted-foreground mb-6 min-h-[40px]">
                                {{ group.description || 'No description provided.' }}
                            </p>

                            <div class="space-y-3 flex-1 flex flex-col min-h-0">
                                <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center justify-between shrink-0">
                                    <span>Access Control</span>
                                    <span class="bg-muted px-2 py-0.5 rounded-full text-[10px]">{{ group.permissions.length }} Active</span>
                                </div>
                                <div class="flex-1 overflow-y-auto pr-2 space-y-2 min-h-[250px] max-h-[400px] custom-scrollbar">
                                    <div v-for="(categoryPerms, category) in groupedPermissions" :key="category" class="rounded-lg border border-white/5 overflow-hidden">
                                        <!-- Header -->
                                        <button 
                                            @click="toggleCategory(category)"
                                            class="w-full flex items-center justify-between p-2 bg-white/5 hover:bg-white/10 transition-colors text-left"
                                        >
                                            <div class="flex items-center gap-2">
                                                <component 
                                                    :is="isCategoryCollapsed(category) ? ChevronRight : ChevronDown" 
                                                    class="h-4 w-4 text-muted-foreground"
                                                />
                                                <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ category }}</h4>
                                            </div>
                                            <span class="text-[10px] bg-muted px-1.5 rounded-sm">{{ categoryPerms.length }}</span>
                                        </button>
                                        
                                        <!-- Body -->
                                        <div v-show="!isCategoryCollapsed(category)" class="p-2 space-y-1 bg-black/5">
                                            <div 
                                                v-for="perm in categoryPerms" 
                                                :key="perm.id"
                                                class="flex items-center justify-between p-2 rounded-lg transition-colors"
                                                :class="hasPermission(group, perm.id) ? 'bg-primary/10 border border-primary/20' : 'bg-white/5 border border-white/5 hover:bg-white/10'"
                                            >
                                                <div class="flex flex-col">
                                                    <span class="text-xs font-medium" :class="hasPermission(group, perm.id) ? 'text-primary' : 'text-muted-foreground'">{{ getPermissionDisplayName(perm) }}</span>
                                                    <span v-if="perm.description" class="text-[10px] text-muted-foreground/60 line-clamp-1">{{ perm.description }}</span>
                                                </div>
                                                <input 
                                                    type="checkbox"
                                                    :checked="hasPermission(group, perm.id)"
                                                    @change="(e: Event) => updateGroupPermission(group, perm.id, (e.target as HTMLInputElement).checked)"
                                                    :disabled="group.name === 'Admin'"
                                                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-white/10 flex justify-end gap-2 shrink-0" v-if="group.name !== 'Admin'">
                             <Button variant="ghost" size="sm" class="h-8 hover:bg-white/20" @click="openDialog(group)">
                                <Pencil class="h-3.5 w-3.5 mr-1.5"/> Rename
                             </Button>
                             <Button variant="ghost" size="sm" class="h-8 text-destructive hover:bg-destructive/10 hover:text-destructive" @click="deleteGroup(group.id)">
                                <Trash2 class="h-3.5 w-3.5 mr-1.5"/> Delete
                             </Button>
                        </div>
                         <div v-else class="mt-4 pt-4 border-t border-white/10 flex justify-end shrink-0">
                            <span class="text-xs text-muted-foreground italic py-1">System Managed</span>
                         </div>
                    </div>
                </div>

                <!-- Delete Confirmation Dialog -->
                <AlertDialog v-model:open="isDeleteDialogOpen">
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Delete User Group?</AlertDialogTitle>
                            <AlertDialogDescription>
                                This action cannot be undone. Deleting this group will remove all permissions from users currently assigned to it.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction @click="confirmDelete" class="bg-destructive hover:bg-destructive/90">
                                Delete Group
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <!-- Dialog (Keeping logic for Create/Rename) -->
                <Dialog v-model:open="isDialogOpen">
                    <DialogContent class="max-w-md">
                        <DialogHeader>
                            <DialogTitle>{{ form.id ? 'Edit Group Details' : 'Create User Group' }}</DialogTitle>
                            <DialogDescription>Update the group name and description. Manage permissions directly on the card.</DialogDescription>
                        </DialogHeader>
                        
                        <div class="space-y-4 py-4">
                            <div class="space-y-2">
                                <Label>Group Name</Label>
                                <Input v-model="form.name" placeholder="e.g. Supervisor" :disabled="form.name === 'Admin'" />
                            </div>
                            <div class="space-y-2">
                                <Label>Description</Label>
                                <Input v-model="form.description" placeholder="Optional description" />
                            </div>
                            <!-- Removed Permissions from Dialog since they are inline now -->
                        </div>

                        <DialogFooter>
                            <Button variant="outline" @click="isDialogOpen = false">Cancel</Button>
                            <Button @click="submitForm">Save Details</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Previous styles remain */
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}

.animate-blob {
  animation: blob 8s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

.glass-panel {
    background: rgba(255, 255, 255, 0.4);
}

.dark .glass-panel {
    background: rgba(0, 0, 0, 0.2);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
</style>
