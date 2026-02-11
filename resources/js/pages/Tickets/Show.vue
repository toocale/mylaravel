<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { ArrowLeft, Send, User, Clock, MapPin, Settings } from 'lucide-vue-next';
import { useTerminology } from '@/composables/useTerminology';

// Use global route function
const route = (window as any).route;

const props = defineProps<{
    ticket: any;
    users: any[];
}>();

const { plant, machine } = useTerminology();

const messageForm = useForm({
    message: '',
    is_internal: false,
});

const updateForm = useForm({
    status: props.ticket.status,
    priority: props.ticket.priority,
    assigned_to: props.ticket.assigned_to,
});

const showUpdateDialog = ref(false);
let pollingInterval: any = null;

const sendMessage = () => {
    messageForm.post(`/tickets/${props.ticket.id}/messages`, {
        preserveScroll: true,
        onSuccess: () => {
            messageForm.reset();
            // The auto-polling will show the new message within 5 seconds
        },
    });
};

const updateTicket = () => {
    updateForm.put(`/tickets/${props.ticket.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showUpdateDialog.value = false;
        },
    });
};

// Auto-refresh to get new messages every 2 seconds for near-instant updates
onMounted(() => {
    pollingInterval = setInterval(() => {
        router.reload({ only: ['ticket'] });
    }, 2000); // Poll every 2 seconds
});

// Clean up polling when component is destroyed
onUnmounted(() => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
});


const getPriorityColor = (priority: string) => {
    const colors: any = {
        urgent: 'bg-red-100 text-red-800 border-red-200',
        high: 'bg-orange-100 text-orange-800 border-orange-200',
        medium: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        low: 'bg-blue-100 text-blue-800 border-blue-200',
    };
    return colors[priority] || 'bg-gray-100 text-gray-800';
};

const getStatusColor = (status: string) => {
    const colors: any = {
        open: 'bg-green-100 text-green-800 border-green-200',
        in_progress: 'bg-blue-100 text-blue-800 border-blue-200',
        resolved: 'bg-purple-100 text-purple-800 border-purple-200',
        closed: 'bg-gray-100 text-gray-800 border-gray-200',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <AppLayout>
        <Head :title="`Ticket #${ticket.id}`" />

        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <Button variant="ghost" @click="router.visit('/tickets')" class="mb-4">
                    <ArrowLeft class="w-4 h-4 mr-2" />
                    Back to Tickets
                </Button>
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-3xl font-bold">Ticket #{{ ticket.id }}</h1>
                        <p class="text-xl mt-2">{{ ticket.subject }}</p>
                    </div>
                    <Button @click="showUpdateDialog = !showUpdateDialog" variant="outline">
                        <Settings class="w-4 h-4 mr-2" />
                        Update Ticket
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Ticket Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Description</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="whitespace-pre-wrap">{{ ticket.description }}</p>
                        </CardContent>
                    </Card>

                    <!-- Messages -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Conversation</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <div 
                                    v-for="message in ticket.messages" 
                                    :key="message.id"
                                    :class="message.user.id === $page.props.auth.user.id ? 'flex justify-end' : 'flex justify-start'"
                                >
                                    <div 
                                        :class="[
                                            'max-w-[70%] rounded-2xl px-4 py-2 shadow-sm',
                                            message.user.id === $page.props.auth.user.id 
                                                ? 'bg-blue-500 text-white rounded-br-sm' 
                                                : 'bg-gray-100 text-gray-900 rounded-bl-sm',
                                            message.is_internal ? 'ring-2 ring-yellow-400' : ''
                                        ]"
                                    >
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-xs font-semibold">{{ message.user.name }}</span>
                                            <span 
                                                :class="message.user.id === $page.props.auth.user.id ? 'text-blue-100' : 'text-gray-500'"
                                                class="text-xs"
                                            >
                                                {{ new Date(message.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) }}
                                            </span>
                                            <Badge v-if="message.is_internal" variant="outline" class="text-xs ml-1">🔒</Badge>
                                        </div>
                                        <p class="text-sm whitespace-pre-wrap">{{ message.message }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Message Form -->
                            <form @submit.prevent="sendMessage" class="mt-6 pt-6 border-t">
                                <Label for="message">Add Message</Label>
                                <Textarea 
                                    id="message"
                                    v-model="messageForm.message"
                                    placeholder="Type your message here..."
                                    rows="4"
                                    class="mt-2"
                                    required
                                />
                                <div class="flex items-center justify-between mt-3">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input 
                                            type="checkbox" 
                                            v-model="messageForm.is_internal"
                                            class="rounded"
                                        />
                                        <span>Internal Note (not visible to ticket creator)</span>
                                    </label>
                                    <Button type="submit" :disabled="messageForm.processing">
                                        <Send class="w-4 h-4 mr-2" />
                                        {{ messageForm.processing ? 'Sending...' : 'Send Message' }}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Status & Priority -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div>
                                <Badge :class="getStatusColor(ticket.status)" class="border text-sm">
                                    {{ ticket.status.replace('_', ' ').toUpperCase() }}
                                </Badge>
                            </div>
                            <div>
                                <Label class="text-xs text-muted-foreground">Priority</Label>
                                <Badge :class="getPriorityColor(ticket.priority)" class="border text-sm mt-1">
                                    {{ ticket.priority.toUpperCase() }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div>
                                <Label class="text-xs text-muted-foreground">Created By</Label>
                                <div class="flex items-center gap-2 mt-1">
                                    <User class="w-4 h-4" />
                                    <span>{{ ticket.creator.name }}</span>
                                </div>
                            </div>

                            <div>
                                <Label class="text-xs text-muted-foreground">Assigned To</Label>
                                <div class="flex items-center gap-2 mt-1">
                                    <User class="w-4 h-4" />
                                    <span>{{ ticket.assignee?.name || 'Unassigned' }}</span>
                                </div>
                            </div>

                            <div>
                                <Label class="text-xs text-muted-foreground">Created</Label>
                                <div class="flex items-center gap-2 mt-1">
                                    <Clock class="w-4 h-4" />
                                    <span>{{ formatDate(ticket.created_at) }}</span>
                                </div>
                            </div>

                            <div v-if="ticket.resolved_at">
                                <Label class="text-xs text-muted-foreground">Resolved</Label>
                                <div class="flex items-center gap-2 mt-1">
                                    <Clock class="w-4 h-4 text-green-600" />
                                    <span>{{ formatDate(ticket.resolved_at) }}</span>
                                </div>
                            </div>

                            <div v-if="ticket.closed_at">
                                <Label class="text-xs text-muted-foreground">Closed</Label>
                                <div class="flex items-center gap-2 mt-1">
                                    <Clock class="w-4 h-4 text-gray-600" />
                                    <span>{{ formatDate(ticket.closed_at) }}</span>
                                </div>
                            </div>

                            <div v-if="ticket.category">
                                <Label class="text-xs text-muted-foreground">Category</Label>
                                <div class="mt-1">{{ ticket.category }}</div>
                            </div>

                            <div v-if="ticket.plant">
                                <Label class="text-xs text-muted-foreground">{{ plant }}</Label>
                                <div class="flex items-center gap-2 mt-1">
                                    <MapPin class="w-4 h-4" />
                                    <span>{{ ticket.plant.name }}</span>
                                </div>
                            </div>

                            <div v-if="ticket.machine">
                                <Label class="text-xs text-muted-foreground">{{ machine }}</Label>
                                <div class="mt-1">{{ ticket.machine.name }}</div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Update Dialog -->
            <div v-if="showUpdateDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showUpdateDialog = false">
                <Card class="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Update Ticket</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="updateTicket" class="space-y-4">
                            <div>
                                <Label for="status">Status</Label>
                                <select 
                                    id="status"
                                    v-model="updateForm.status"
                                    class="w-full mt-1 h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                    <option v-if="ticket.created_by === $page.props.auth.user.id || ticket.assigned_to === $page.props.auth.user.id" value="closed">Closed</option>
                                </select>
                            </div>

                            <div>
                                <Label for="priority">Priority</Label>
                                <select 
                                    id="priority"
                                    v-model="updateForm.priority"
                                    class="w-full mt-1 h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>

                            <div>
                                <Label for="assigned_to">Assign To</Label>
                                <select 
                                    id="assigned_to"
                                    v-model="updateForm.assigned_to"
                                    class="w-full mt-1 h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option :value="null">Unassigned</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex justify-end gap-3 pt-4">
                                <Button type="button" variant="outline" @click="showUpdateDialog = false">
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="updateForm.processing">
                                    {{ updateForm.processing ? 'Updating...' : 'Update' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
