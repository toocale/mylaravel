<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import {
    Plus, Pencil, Trash2, AlertTriangle, XCircle, Info, Bell, Shield,
    CheckCircle, Clock, Activity
} from 'lucide-vue-next';

interface AlertRule {
    id: number;
    name: string;
    type: string;
    severity: string;
    threshold: number;
    duration_minutes: number;
    scope_type: string | null;
    scope_id: number | null;
    is_active: boolean;
    notify_email: boolean;
    cooldown_minutes: number;
    alerts_count?: number;
}

const rules = ref<AlertRule[]>([]);
const loading = ref(false);
const showModal = ref(false);
const editingRule = ref<AlertRule | null>(null);

const form = ref({
    name: '',
    type: 'oee_below_target',
    severity: 'warning',
    threshold: 80,
    duration_minutes: 15,
    scope_type: '',
    scope_id: null as number | null,
    is_active: true,
    notify_email: false,
    cooldown_minutes: 30,
});

const ruleTypes = [
    { value: 'oee_below_target', label: 'OEE Below Target', description: 'Alert when OEE drops below threshold %', unit: '%' },
    { value: 'machine_stopped', label: 'Machine Stopped', description: 'Alert when machine is stopped for too long', unit: 'min' },
    { value: 'excessive_downtime', label: 'Excessive Downtime', description: 'Alert when downtime exceeds threshold minutes', unit: 'min' },
    { value: 'quality_drop', label: 'Quality Drop', description: 'Alert when reject rate exceeds threshold %', unit: '%' },
    { value: 'performance_drop', label: 'Performance Drop', description: 'Alert when performance drops below threshold % of ideal', unit: '%' },
];

const severityOptions = [
    { value: 'critical', label: 'Critical', color: 'text-red-500' },
    { value: 'warning', label: 'Warning', color: 'text-amber-500' },
    { value: 'info', label: 'Info', color: 'text-blue-500' },
];

const severityConfig: Record<string, any> = {
    critical: { icon: XCircle, color: 'text-red-500', bg: 'bg-red-500/10' },
    warning: { icon: AlertTriangle, color: 'text-amber-500', bg: 'bg-amber-500/10' },
    info: { icon: Info, color: 'text-blue-500', bg: 'bg-blue-500/10' },
};

const fetchRules = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/v1/andon/rules');
        rules.value = res.data.rules || [];
    } catch (e) {
        console.error('Fetch rules error:', e);
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    editingRule.value = null;
    form.value = {
        name: '',
        type: 'oee_below_target',
        severity: 'warning',
        threshold: 80,
        duration_minutes: 15,
        scope_type: '',
        scope_id: null,
        is_active: true,
        notify_email: false,
        cooldown_minutes: 30,
    };
    showModal.value = true;
};

const openEdit = (rule: AlertRule) => {
    editingRule.value = rule;
    form.value = {
        name: rule.name,
        type: rule.type,
        severity: rule.severity,
        threshold: rule.threshold,
        duration_minutes: rule.duration_minutes,
        scope_type: rule.scope_type || '',
        scope_id: rule.scope_id,
        is_active: rule.is_active,
        notify_email: rule.notify_email,
        cooldown_minutes: rule.cooldown_minutes,
    };
    showModal.value = true;
};

const saveRule = async () => {
    try {
        const payload = {
            ...form.value,
            scope_type: form.value.scope_type || null,
        };

        if (editingRule.value) {
            await axios.put(`/api/v1/andon/rules/${editingRule.value.id}`, payload);
        } else {
            await axios.post('/api/v1/andon/rules', payload);
        }
        showModal.value = false;
        await fetchRules();
    } catch (e: any) {
        console.error('Save rule error:', e);
        alert(e.response?.data?.message || 'Failed to save rule');
    }
};

const deleteRule = async (ruleId: number) => {
    if (!confirm('Are you sure you want to delete this alert rule?')) return;
    try {
        await axios.delete(`/api/v1/andon/rules/${ruleId}`);
        await fetchRules();
    } catch (e) {
        console.error('Delete rule error:', e);
    }
};

const toggleRule = async (rule: AlertRule) => {
    try {
        await axios.put(`/api/v1/andon/rules/${rule.id}`, { is_active: !rule.is_active });
        await fetchRules();
    } catch (e) {
        console.error('Toggle rule error:', e);
    }
};

const getRuleType = (type: string) => ruleTypes.find(t => t.value === type);

onMounted(fetchRules);
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Admin', href: '/admin/settings' }, { title: 'Alert Rules', href: '/admin/alert-rules' }]">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                        <Shield class="h-6 w-6 text-amber-500" />
                        Alert Rules
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Configure automated alert rules for the Andon board. Alerts are evaluated every 2 minutes.
                    </p>
                </div>
                <Button @click="openCreate" class="gap-2">
                    <Plus class="h-4 w-4" />
                    New Rule
                </Button>
            </div>

            <!-- Rules Grid -->
            <div v-if="rules.length > 0" class="grid gap-4 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
                <Card v-for="rule in rules" :key="rule.id"
                      class="relative transition-all"
                      :class="{ 'opacity-50': !rule.is_active }">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2 min-w-0">
                                <component :is="severityConfig[rule.severity]?.icon || Info"
                                           class="h-5 w-5 shrink-0"
                                           :class="severityConfig[rule.severity]?.color || 'text-blue-500'" />
                                <CardTitle class="text-base truncate">{{ rule.name }}</CardTitle>
                            </div>
                            <Switch :checked="rule.is_active" @update:checked="toggleRule(rule)" />
                        </div>
                        <CardDescription class="mt-1">
                            {{ getRuleType(rule.type)?.description || rule.type }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Threshold</span>
                                <span class="font-semibold">{{ rule.threshold }}{{ getRuleType(rule.type)?.unit === '%' ? '%' : ' min' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Duration</span>
                                <span class="font-mono text-xs">{{ rule.duration_minutes }} min</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Cooldown</span>
                                <span class="font-mono text-xs">{{ rule.cooldown_minutes }} min</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Severity</span>
                                <Badge :variant="rule.severity === 'critical' ? 'destructive' : (rule.severity === 'warning' ? 'secondary' : 'outline')"
                                       class="text-xs uppercase">{{ rule.severity }}</Badge>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Scope</span>
                                <span class="text-xs">{{ rule.scope_type ? `${rule.scope_type} #${rule.scope_id}` : 'Global' }}</span>
                            </div>
                            <div v-if="rule.alerts_count" class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Active Alerts</span>
                                <Badge variant="destructive" class="text-xs">{{ rule.alerts_count }}</Badge>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-4 pt-3 border-t">
                            <Button size="sm" variant="outline" class="flex-1 gap-1" @click="openEdit(rule)">
                                <Pencil class="h-3 w-3" /> Edit
                            </Button>
                            <Button size="sm" variant="ghost" class="text-destructive hover:text-destructive" @click="deleteRule(rule.id)">
                                <Trash2 class="h-3 w-3" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <div v-else-if="!loading" class="flex flex-col items-center justify-center py-20 border rounded-xl bg-muted/20">
                <Bell class="h-16 w-16 text-muted-foreground/20 mb-4" />
                <h3 class="text-lg font-semibold mb-1">No Alert Rules</h3>
                <p class="text-sm text-muted-foreground mb-4">Create your first alert rule to start monitoring your machines.</p>
                <Button @click="openCreate" class="gap-2">
                    <Plus class="h-4 w-4" /> Create Alert Rule
                </Button>
            </div>

            <!-- Create/Edit Modal -->
            <Dialog :open="showModal" @update:open="showModal = $event">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{ editingRule ? 'Edit Alert Rule' : 'New Alert Rule' }}</DialogTitle>
                        <DialogDescription>
                            Configure when alerts should be triggered. Rules are evaluated every 2 minutes.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 py-2">
                        <div class="space-y-2">
                            <Label>Rule Name</Label>
                            <Input v-model="form.name" placeholder="e.g., OEE Critical Alert" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Rule Type</Label>
                                <Select v-model="form.type">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="t in ruleTypes" :key="t.value" :value="t.value">
                                            {{ t.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label>Severity</Label>
                                <Select v-model="form.severity">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="s in severityOptions" :key="s.value" :value="s.value">
                                            {{ s.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="space-y-2">
                                <Label>Threshold ({{ getRuleType(form.type)?.unit || '' }})</Label>
                                <Input v-model.number="form.threshold" type="number" min="0" />
                            </div>
                            <div class="space-y-2">
                                <Label>Duration (min)</Label>
                                <Input v-model.number="form.duration_minutes" type="number" min="0" />
                            </div>
                            <div class="space-y-2">
                                <Label>Cooldown (min)</Label>
                                <Input v-model.number="form.cooldown_minutes" type="number" min="1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Scope (Optional)</Label>
                                <Select v-model="form.scope_type">
                                    <SelectTrigger><SelectValue placeholder="Global (all machines)" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Global</SelectItem>
                                        <SelectItem value="plant">Plant</SelectItem>
                                        <SelectItem value="line">Line</SelectItem>
                                        <SelectItem value="machine">Machine</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div v-if="form.scope_type" class="space-y-2">
                                <Label>{{ form.scope_type }} ID</Label>
                                <Input v-model.number="form.scope_id" type="number" placeholder="ID" />
                            </div>
                        </div>
                        <div class="flex items-center gap-6 pt-2">
                            <div class="flex items-center gap-2">
                                <Switch v-model:checked="form.is_active" />
                                <Label class="text-sm">Enabled</Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <Switch v-model:checked="form.notify_email" />
                                <Label class="text-sm">Email Notification</Label>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="showModal = false">Cancel</Button>
                        <Button @click="saveRule" :disabled="!form.name">
                            {{ editingRule ? 'Update Rule' : 'Create Rule' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
