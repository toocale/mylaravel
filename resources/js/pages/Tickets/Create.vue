<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { ArrowLeft } from 'lucide-vue-next';
import { useTerminology } from '@/composables/useTerminology';

// Use global route function
const route = (window as any).route;


const props = defineProps<{
    users: any[];
    plants: any[];
}>();

const { plant, machine } = useTerminology();

const form = useForm({
    subject: '',
    description: '',
    priority: 'medium',
    category: '',
    assigned_to: null as number | null,
    plant_id: null as number | null,
    machine_id: null as number | null,
});

const selectedPlant = ref<any>(null);
const availableMachines = ref<any[]>([]);

const onPlantChange = (plantId: number | null) => {
    form.plant_id = plantId;
    form.machine_id = null;
    
    if (plantId) {
        selectedPlant.value = props.plants.find(p => p.id === plantId);
        // Get all machines from all lines in the plant
        availableMachines.value = selectedPlant.value?.lines?.flatMap((line: any) => line.machines || []) || [];
    } else {
        selectedPlant.value = null;
        availableMachines.value = [];
    }
};

const submit = () => {
    form.post('/tickets');
};
</script>

<template>
    <AppLayout>
        <Head title="Create Ticket" />

        <div class="p-6 max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-6">
                <Button variant="ghost" @click="router.visit('/tickets')" class="mb-4">
                    <ArrowLeft class="w-4 h-4 mr-2" />
                    Back to Tickets
                </Button>
                <h1 class="text-3xl font-bold">Create New Ticket</h1>
                <p class="text-muted-foreground mt-1">Report an issue or request assistance</p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Ticket Details</CardTitle>
                    <CardDescription>Provide as much information as possible to help us assist you</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Subject -->
                        <div>
                            <Label for="subject">
                                Subject <span class="text-red-500">*</span>
                            </Label>
                            <Input 
                                id="subject"
                                v-model="form.subject" 
                                placeholder="Brief description of the issue"
                                required
                                class="mt-1"
                            />
                            <p v-if="form.errors.subject" class="text-sm text-red-500 mt-1">{{ form.errors.subject }}</p>
                        </div>

                        <!-- Description -->
                        <div>
                            <Label for="description">
                                Description <span class="text-red-500">*</span>
                            </Label>
                            <Textarea 
                                id="description"
                                v-model="form.description" 
                                placeholder="Detailed description of the issue..."
                                rows="6"
                                required
                                class="mt-1"
                            />
                            <p v-if="form.errors.description" class="text-sm text-red-500 mt-1">{{ form.errors.description }}</p>
                        </div>

                        <!-- Priority & Category -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label for="priority">
                                    Priority <span class="text-red-500">*</span>
                                </Label>
                                <select 
                                    id="priority"
                                    v-model="form.priority"
                                    class="w-full mt-1 h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>

                            <div>
                                <Label for="category">Category</Label>
                                <Input 
                                    id="category"
                                    v-model="form.category" 
                                    placeholder="e.g., Technical, Maintenance"
                                    class="mt-1"
                                />
                            </div>
                        </div>

                        <!-- Assignment -->
                        <div>
                            <Label for="assigned_to">Assign To (Optional)</Label>
                            <select 
                                id="assigned_to"
                                v-model="form.assigned_to"
                                class="w-full mt-1 h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option :value="null">Unassigned</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                        </div>

                        <!-- Context (Plant & Machine) -->
                        <div class="border-t pt-6">
                            <h3 class="text-sm font-semibold mb-4">Context (Optional)</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <Label for="plant_id">{{ plant }}</Label>
                                    <select 
                                        id="plant_id"
                                        :value="form.plant_id"
                                        @change="onPlantChange(($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
                                        class="w-full mt-1 h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option :value="null">Select {{ plant }}</option>
                                        <option v-for="plant in plants" :key="plant.id" :value="plant.id">
                                            {{ plant.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="machine_id">{{ machine }}</Label>
                                    <select 
                                        id="machine_id"
                                        v-model="form.machine_id"
                                        :disabled="!form.plant_id"
                                        class="w-full mt-1 h-10 rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-50"
                                    >
                                        <option :value="null">Select {{ machine }}</option>
                                        <option v-for="machine in availableMachines" :key="machine.id" :value="machine.id">
                                            {{ machine.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <Button 
                                type="button" 
                                variant="outline" 
                                @click="router.visit('/tickets')"
                            >
                                Cancel
                            </Button>
                            <Button 
                                type="submit" 
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Creating...' : 'Create Ticket' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
