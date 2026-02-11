<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { 
    Mail, Plus, Trash2, Edit2, Send, Clock, Calendar, 
    Building2, Cpu, LayoutGrid, Play, Pause, TestTube2, X
} from 'lucide-vue-next';

interface Plant {
    id: number;
    name: string;
    lines: {
        id: number;
        name: string;
        machines: { id: number; name: string }[];
    }[];
}

interface Schedule {
    id: number;
    name: string;
    report_type: string;
    report_type_label: string;
    frequency: string;
    frequency_label: string;
    send_time: string;
    recipients: string[];
    plant_id: number | null;
    line_id: number | null;
    machine_id: number | null;
    is_active: boolean;
    last_sent_at: string | null;
    plant?: { id: number; name: string };
    line?: { id: number; name: string };
    machine?: { id: number; name: string };
}

const props = defineProps<{
    schedules: Schedule[];
    plants: Plant[];
}>();

const showCreateDialog = ref(false);
const editingSchedule = ref<Schedule | null>(null);
const showSendNowDialog = ref(false);

const form = useForm({
    name: '',
    report_type: 'daily_oee',
    frequency: 'daily',
    send_time: '08:00',
    recipients: [''],
    plant_id: null as number | null,
    line_id: null as number | null,
    machine_id: null as number | null,
    is_active: true,
});

const sendNowForm = useForm({
    report_type: 'daily_oee',
    recipients: [''],
    plant_id: null as number | null,
    line_id: null as number | null,
    machine_id: null as number | null,
});

// Dynamic line/machine options based on selection
const availableLines = computed(() => {
    if (!form.plant_id) return [];
    const plant = props.plants.find(p => p.id === form.plant_id);
    return plant?.lines || [];
});

const availableMachines = computed(() => {
    if (!form.line_id) return [];
    const plant = props.plants.find(p => p.id === form.plant_id);
    const line = plant?.lines.find(l => l.id === form.line_id);
    return line?.machines || [];
});

const sendNowLines = computed(() => {
    if (!sendNowForm.plant_id) return [];
    const plant = props.plants.find(p => p.id === sendNowForm.plant_id);
    return plant?.lines || [];
});

const sendNowMachines = computed(() => {
    if (!sendNowForm.line_id) return [];
    const plant = props.plants.find(p => p.id === sendNowForm.plant_id);
    const line = plant?.lines.find(l => l.id === sendNowForm.line_id);
    return line?.machines || [];
});

// Reset child selections when parent changes
watch(() => form.plant_id, () => {
    form.line_id = null;
    form.machine_id = null;
});

watch(() => form.line_id, () => {
    form.machine_id = null;
});

watch(() => sendNowForm.plant_id, () => {
    sendNowForm.line_id = null;
    sendNowForm.machine_id = null;
});

watch(() => sendNowForm.line_id, () => {
    sendNowForm.machine_id = null;
});

const resetForm = () => {
    form.reset();
    form.recipients = [''];
    editingSchedule.value = null;
};

const openEditDialog = (schedule: Schedule) => {
    editingSchedule.value = schedule;
    form.name = schedule.name;
    form.report_type = schedule.report_type;
    form.frequency = schedule.frequency;
    form.send_time = schedule.send_time.substring(0, 5);
    form.recipients = schedule.recipients.length ? schedule.recipients : [''];
    form.plant_id = schedule.plant_id;
    form.line_id = schedule.line_id;
    form.machine_id = schedule.machine_id;
    form.is_active = schedule.is_active;
    showCreateDialog.value = true;
};

const addRecipient = () => {
    form.recipients.push('');
};

const removeRecipient = (index: number) => {
    if (form.recipients.length > 1) {
        form.recipients.splice(index, 1);
    }
};

const addSendNowRecipient = () => {
    sendNowForm.recipients.push('');
};

const removeSendNowRecipient = (index: number) => {
    if (sendNowForm.recipients.length > 1) {
        sendNowForm.recipients.splice(index, 1);
    }
};

const submitForm = () => {
    const filteredRecipients = form.recipients.filter(r => r.trim() !== '');
    form.recipients = filteredRecipients;
    
    if (editingSchedule.value) {
        form.put(`/admin/report-delivery/${editingSchedule.value.id}`, {
            onSuccess: () => {
                showCreateDialog.value = false;
                resetForm();
            }
        });
    } else {
        form.post('/admin/report-delivery', {
            onSuccess: () => {
                showCreateDialog.value = false;
                resetForm();
            }
        });
    }
};

const deleteSchedule = (schedule: Schedule) => {
    if (confirm('Are you sure you want to delete this schedule?')) {
        router.delete(`/admin/report-delivery/${schedule.id}`);
    }
};

const toggleSchedule = (schedule: Schedule) => {
    router.post(`/admin/report-delivery/${schedule.id}/toggle`);
};

const sendTestEmail = (schedule: Schedule) => {
    router.post(`/admin/report-delivery/${schedule.id}/test`);
};

const submitSendNow = () => {
    const filteredRecipients = sendNowForm.recipients.filter(r => r.trim() !== '');
    sendNowForm.recipients = filteredRecipients;
    
    sendNowForm.post('/admin/report-delivery/send-now', {
        onSuccess: () => {
            showSendNowDialog.value = false;
            sendNowForm.reset();
            sendNowForm.recipients = [''];
        }
    });
};

const getReportTypeIcon = (type: string) => {
    switch (type) {
        case 'shift': return Clock;
        case 'daily_oee': return LayoutGrid;
        case 'downtime': return Pause;
        case 'production': return Cpu;
        default: return Mail;
    }
};

const getFrequencyColor = (frequency: string) => {
    switch (frequency) {
        case 'daily': return 'bg-blue-100 text-blue-700 border-blue-200';
        case 'weekly': return 'bg-purple-100 text-purple-700 border-purple-200';
        case 'monthly': return 'bg-amber-100 text-amber-700 border-amber-200';
        case 'shift_end': return 'bg-green-100 text-green-700 border-green-200';
        default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
};

const getScopeLabel = (schedule: Schedule) => {
    if (schedule.machine) return schedule.machine.name;
    if (schedule.line) return schedule.line.name;
    if (schedule.plant) return schedule.plant.name;
    return 'All Plants';
};

const formatTime = (time: string) => {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
};

const formatDate = (dateStr: string | null) => {
    if (!dateStr) return 'Never';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head title="Email Report Delivery" />

    <div class="p-6 space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-primary to-purple-600 bg-clip-text text-transparent">
                    Email Report Delivery
                </h1>
                <p class="text-muted-foreground mt-1">
                    Schedule automatic report delivery to your inbox
                </p>
            </div>
            <div class="flex gap-3">
                <Dialog v-model:open="showSendNowDialog">
                    <DialogTrigger as-child>
                        <Button variant="outline" class="gap-2">
                            <Send class="h-4 w-4" />
                            Send Now
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Send Report Now</DialogTitle>
                            <DialogDescription>
                                Send a one-time report immediately to specified recipients.
                            </DialogDescription>
                        </DialogHeader>
                        <form @submit.prevent="submitSendNow" class="space-y-4">
                            <div class="space-y-2">
                                <Label>Report Type</Label>
                                <Select v-model="sendNowForm.report_type">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="daily_oee">Daily OEE Summary</SelectItem>
                                        <SelectItem value="shift">Shift Report</SelectItem>
                                        <SelectItem value="downtime">Downtime Analysis</SelectItem>
                                        <SelectItem value="production">Production Summary</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            
                            <div class="space-y-2">
                                <Label>Recipients</Label>
                                <div v-for="(email, index) in sendNowForm.recipients" :key="index" class="flex gap-2">
                                    <Input 
                                        v-model="sendNowForm.recipients[index]" 
                                        type="email" 
                                        placeholder="email@example.com"
                                        class="flex-1"
                                    />
                                    <Button 
                                        type="button" 
                                        variant="ghost" 
                                        size="icon"
                                        @click="removeSendNowRecipient(index)"
                                        :disabled="sendNowForm.recipients.length === 1"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                                <Button type="button" variant="outline" size="sm" @click="addSendNowRecipient" class="w-full">
                                    <Plus class="h-4 w-4 mr-2" />
                                    Add Recipient
                                </Button>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div class="space-y-2">
                                    <Label>Plant</Label>
                                    <Select v-model="sendNowForm.plant_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="All" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="null">All Plants</SelectItem>
                                            <SelectItem v-for="plant in plants" :key="plant.id" :value="plant.id">
                                                {{ plant.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>Line</Label>
                                    <Select v-model="sendNowForm.line_id" :disabled="!sendNowForm.plant_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="All" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="null">All Lines</SelectItem>
                                            <SelectItem v-for="line in sendNowLines" :key="line.id" :value="line.id">
                                                {{ line.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>Machine</Label>
                                    <Select v-model="sendNowForm.machine_id" :disabled="!sendNowForm.line_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="All" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="null">All Machines</SelectItem>
                                            <SelectItem v-for="machine in sendNowMachines" :key="machine.id" :value="machine.id">
                                                {{ machine.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            
                            <DialogFooter>
                                <Button type="button" variant="outline" @click="showSendNowDialog = false">
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="sendNowForm.processing" class="gap-2">
                                    <Send class="h-4 w-4" />
                                    Send Now
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog v-model:open="showCreateDialog">
                    <DialogTrigger as-child>
                        <Button @click="resetForm" class="gap-2 bg-gradient-to-r from-primary to-purple-600 hover:from-primary/90 hover:to-purple-600/90">
                            <Plus class="h-4 w-4" />
                            New Schedule
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-2xl max-h-[90vh]">
                        <DialogHeader>
                            <DialogTitle>{{ editingSchedule ? 'Edit Schedule' : 'Create Report Schedule' }}</DialogTitle>
                            <DialogDescription>
                                Configure automatic email delivery for OEE reports.
                            </DialogDescription>
                        </DialogHeader>
                        <ScrollArea class="max-h-[60vh] pr-4">
                            <form @submit.prevent="submitForm" class="space-y-5 py-2">
                                <div class="space-y-2">
                                    <Label for="name">Schedule Name</Label>
                                    <Input 
                                        id="name" 
                                        v-model="form.name" 
                                        placeholder="e.g., Daily Plant A Summary"
                                        :class="{ 'border-red-500': form.errors.name }"
                                    />
                                    <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label>Report Type</Label>
                                        <Select v-model="form.report_type">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="daily_oee">Daily OEE Summary</SelectItem>
                                                <SelectItem value="shift">Shift Report</SelectItem>
                                                <SelectItem value="downtime">Downtime Analysis</SelectItem>
                                                <SelectItem value="production">Production Summary</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Frequency</Label>
                                        <Select v-model="form.frequency">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="daily">Daily</SelectItem>
                                                <SelectItem value="weekly">Weekly</SelectItem>
                                                <SelectItem value="monthly">Monthly</SelectItem>
                                                <SelectItem value="shift_end">End of Shift</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="send_time">Send Time</Label>
                                    <Input 
                                        id="send_time" 
                                        type="time" 
                                        v-model="form.send_time"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label>Recipients</Label>
                                    <div class="space-y-2">
                                        <div v-for="(email, index) in form.recipients" :key="index" class="flex gap-2">
                                            <Input 
                                                v-model="form.recipients[index]" 
                                                type="email" 
                                                placeholder="email@example.com"
                                                class="flex-1"
                                            />
                                            <Button 
                                                type="button" 
                                                variant="ghost" 
                                                size="icon"
                                                @click="removeRecipient(index)"
                                                :disabled="form.recipients.length === 1"
                                            >
                                                <X class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    <Button type="button" variant="outline" size="sm" @click="addRecipient" class="w-full">
                                        <Plus class="h-4 w-4 mr-2" />
                                        Add Recipient
                                    </Button>
                                    <p v-if="form.errors.recipients" class="text-sm text-red-500">{{ form.errors.recipients }}</p>
                                </div>

                                <div class="space-y-3 p-4 bg-muted/50 rounded-lg">
                                    <Label class="text-sm font-semibold">Report Scope</Label>
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="space-y-2">
                                            <Label class="text-xs text-muted-foreground">Plant</Label>
                                            <Select v-model="form.plant_id">
                                                <SelectTrigger>
                                                    <SelectValue placeholder="All Plants" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem :value="null">All Plants</SelectItem>
                                                    <SelectItem v-for="plant in plants" :key="plant.id" :value="plant.id">
                                                        {{ plant.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="space-y-2">
                                            <Label class="text-xs text-muted-foreground">Line</Label>
                                            <Select v-model="form.line_id" :disabled="!form.plant_id">
                                                <SelectTrigger>
                                                    <SelectValue placeholder="All Lines" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem :value="null">All Lines</SelectItem>
                                                    <SelectItem v-for="line in availableLines" :key="line.id" :value="line.id">
                                                        {{ line.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="space-y-2">
                                            <Label class="text-xs text-muted-foreground">Machine</Label>
                                            <Select v-model="form.machine_id" :disabled="!form.line_id">
                                                <SelectTrigger>
                                                    <SelectValue placeholder="All Machines" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem :value="null">All Machines</SelectItem>
                                                    <SelectItem v-for="machine in availableMachines" :key="machine.id" :value="machine.id">
                                                        {{ machine.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between p-4 bg-muted/50 rounded-lg">
                                    <div>
                                        <Label for="is_active" class="font-semibold">Active</Label>
                                        <p class="text-sm text-muted-foreground">Enable or disable this schedule</p>
                                    </div>
                                    <Switch id="is_active" v-model:checked="form.is_active" />
                                </div>
                            </form>
                        </ScrollArea>
                        <DialogFooter class="mt-4">
                            <Button type="button" variant="outline" @click="showCreateDialog = false; resetForm()">
                                Cancel
                            </Button>
                            <Button @click="submitForm" :disabled="form.processing" class="gap-2">
                                <Mail class="h-4 w-4" />
                                {{ editingSchedule ? 'Update Schedule' : 'Create Schedule' }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <!-- Schedules Grid -->
        <div v-if="schedules.length === 0" class="text-center py-16">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary/10 mb-6">
                <Mail class="h-10 w-10 text-primary" />
            </div>
            <h3 class="text-xl font-semibold mb-2">No Report Schedules</h3>
            <p class="text-muted-foreground mb-6">
                Create your first schedule to receive automatic OEE reports via email.
            </p>
            <Button @click="showCreateDialog = true; resetForm()" class="gap-2">
                <Plus class="h-4 w-4" />
                Create Your First Schedule
            </Button>
        </div>

        <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Card 
                v-for="schedule in schedules" 
                :key="schedule.id"
                class="group hover:shadow-lg transition-all duration-300 border-2"
                :class="schedule.is_active ? 'border-transparent hover:border-primary/20' : 'border-dashed border-muted-foreground/30 opacity-60'"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div 
                                class="p-2.5 rounded-xl transition-colors"
                                :class="schedule.is_active ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
                            >
                                <component :is="getReportTypeIcon(schedule.report_type)" class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle class="text-base">{{ schedule.name }}</CardTitle>
                                <CardDescription class="text-xs">
                                    {{ schedule.report_type_label }}
                                </CardDescription>
                            </div>
                        </div>
                        <Switch 
                            :checked="schedule.is_active" 
                            @update:checked="toggleSchedule(schedule)"
                        />
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        <Badge variant="outline" :class="getFrequencyColor(schedule.frequency)">
                            <Calendar class="h-3 w-3 mr-1" />
                            {{ schedule.frequency_label }}
                        </Badge>
                        <Badge variant="outline" class="bg-slate-100 text-slate-700 border-slate-200">
                            <Clock class="h-3 w-3 mr-1" />
                            {{ formatTime(schedule.send_time) }}
                        </Badge>
                    </div>

                    <div class="text-sm space-y-2">
                        <div class="flex items-center gap-2 text-muted-foreground">
                            <Building2 class="h-4 w-4" />
                            <span>{{ getScopeLabel(schedule) }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-muted-foreground">
                            <Mail class="h-4 w-4" />
                            <span class="truncate">{{ schedule.recipients.join(', ') }}</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t text-xs text-muted-foreground">
                        Last sent: {{ formatDate(schedule.last_sent_at) }}
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            class="flex-1 gap-1.5"
                            @click="sendTestEmail(schedule)"
                        >
                            <TestTube2 class="h-3.5 w-3.5" />
                            Test
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm"
                            @click="openEditDialog(schedule)"
                        >
                            <Edit2 class="h-3.5 w-3.5" />
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm"
                            class="text-red-600 hover:text-red-700 hover:bg-red-50"
                            @click="deleteSchedule(schedule)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
