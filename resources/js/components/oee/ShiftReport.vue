<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { User, Clock, Calendar, RefreshCw, Loader2, FileWarning, Edit, History, PackageX, Package, ArrowRight, Plus, X, AlertCircle } from 'lucide-vue-next';
import axios from 'axios';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale
} from 'chart.js'
import { Bar } from 'vue-chartjs'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const props = defineProps<{
    machineId: number;
    initialData?: any; // Optional: Pass data directly if already available
    products?: any[];
    users?: any[];
    shifts?: any[];
    reasonCodes?: any[];
}>();

const loading = ref(true);
const error = ref<string | null>(null);
const report = ref<any>(null);
const shiftHistory = ref<any[]>([]);

const currentDateShifts = computed(() => {
    if (!shiftHistory.value) return [];
    
    // 1. Filter for selected date
    const rawShifts = shiftHistory.value.filter(s => s.started_at && s.started_at.startsWith(date.value));
    
    // 2. Group by Shift Name (e.g. "Day Shift", "Night Shift")
    const groups: Record<string, any> = {};
    
    rawShifts.forEach(shift => {
        // API returns shift_name, check that first
        const name = shift.shift_name || shift.shift_type || 'Unknown Shift';
        
        if (!groups[name]) {
            groups[name] = {
                id: name, // Unique ID for key
                shiftName: name,
                started_at: shift.started_at, // Sort by earliest occurrence
                good_count: 0,
                reject_count: 0,
                total_count: 0,
                downtime_minutes: 0,
                shifts_aggregated: 0
            };
        }
        
        const good = parseInt(shift.good_count) || 0;
        const reject = parseInt(shift.reject_count) || 0;
        const total = parseInt(shift.total_count) || (good + reject);
        const dt = parseFloat(shift.downtime_minutes) || 0;
        
        groups[name].good_count += good;
        groups[name].reject_count += reject;
        groups[name].total_count += total;
        groups[name].downtime_minutes += dt;
        groups[name].shifts_aggregated++;
        
        // Keep earliest start time for sorting
        if (new Date(shift.started_at) < new Date(groups[name].started_at)) {
            groups[name].started_at = shift.started_at;
        }
    });

    // 3. Return as Array sorted by time
    return Object.values(groups).sort((a, b) => new Date(a.started_at).getTime() - new Date(b.started_at).getTime());
});

const comparisonChartData = computed(() => {
    const shifts = currentDateShifts.value;
    return {
        labels: shifts.map(s => s.shiftName || 'Shift ' + s.id),
        datasets: [
            {
                label: 'Good Units',
                backgroundColor: '#10b981', // emerald-500
                data: shifts.map(s => s.good_count),
                borderRadius: 4
            },
            {
                label: 'Reject Units',
                backgroundColor: '#ef4444', // red-500
                data: shifts.map(s => s.reject_count),
                borderRadius: 4
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
            align: 'end' as const,
            labels: {
                usePointStyle: true,
                boxWidth: 8,
                font: { size: 11 }
            }
        },
        tooltip: {
            mode: 'index' as const,
            intersect: false,
        }
    },
    scales: {
        x: {
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            grid: { 
                display: true,
                color: 'rgba(0, 0, 0, 0.1)'
            }
        }
    }
} as const;

const reportType = ref<'daily' | 'range'>('daily');
const date = ref(new Date().toISOString().split('T')[0]);
const startDate = ref(new Date().toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);

// Edit functionality
const editDialogOpen = ref(false);
const editingShift = ref<any>(null);
const editForm = ref({
    good_count: 0,
    reject_count: 0,
    shift_id: null as number | null,
    product_id: null as number | null,
    user_id: null as number | null,
    started_at: '',
    ended_at: '',
    material_loss_units: 0,
    downtime_minutes: 0,
    batch_number: '',
    comment: '',
    initial_run: {
        good_count: 0,
        reject_count: 0,
        material_loss_units: 0,
        batch_number: ''
    },
    changeovers: [] as any[],
    downtime_records: [] as any[],
    product_counts: [] as any[]
});
const editLoading = ref(false);



// Edit history
const historyDialogOpen = ref(false);
const viewingHistory = ref<any>(null);

const editHistory = ref<any[]>([]);

const setRange = (type: 'today' | 'yesterday' | 'week' | 'month' | 'last_month') => {
    const today = new Date();
    
    if (type === 'today') {
        reportType.value = 'daily';
        date.value = today.toISOString().split('T')[0];
    } else if (type === 'yesterday') {
        reportType.value = 'daily';
        const y = new Date(today);
        y.setDate(y.getDate() - 1);
        date.value = y.toISOString().split('T')[0];
    } else {
        reportType.value = 'range';
        endDate.value = today.toISOString().split('T')[0];
        
        const start = new Date(today);
        if (type === 'week') {
            const day = start.getDay() || 7; // Get current day number, converting Sun(0) to 7
            if (day !== 1) start.setHours(-24 * (day - 1)); // Set to previous Monday
        } else if (type === 'month') {
            start.setDate(1); // First day of month
        } else if (type === 'last_month') {
            start.setMonth(start.getMonth() - 1);
            start.setDate(1);
            const end = new Date(today);
            end.setDate(0); // Last day of previous month
            endDate.value = end.toISOString().split('T')[0];
        }
        startDate.value = start.toISOString().split('T')[0];
    }
    // Auto-fetch will trigger via watchers
};

const fetchReport = async () => {
    if (!props.machineId) return;
    loading.value = true;
    error.value = null;
    try {
        const params: any = { machine_id: props.machineId };
        
        if (reportType.value === 'daily') {
            params.date = date.value;
        } else {
            params.start_date = startDate.value;
            params.end_date = endDate.value;
            params.mode = 'aggregate'; // Tell backend to aggregate
        }

        const res = await axios.get('/api/v1/dashboard/report', { params });
        report.value = res.data;
    } catch (e: any) {
        console.error(e);
        error.value = e.response?.data?.message || 'No data found for this period.';
    } finally {
        loading.value = false;
    }
};

const fetchShiftHistory = async () => {
    if (!props.machineId) return;
    try {
        const res = await axios.get(`/admin/production-shifts/${props.machineId}/history`);
        shiftHistory.value = res.data.shifts || [];
    } catch (e: any) {
        console.error('Failed to load shift history:', e);
    }
};

// Refresh both report and shift history
const refresh = async () => {
    await Promise.all([
        fetchReport(),
        fetchShiftHistory()
    ]);
};

// Expose refresh method so parent can call it
defineExpose({
    refresh
});

onMounted(() => {
    fetchReport();
    fetchShiftHistory();
});

watch(() => props.machineId, () => {
    fetchReport();
    fetchShiftHistory();
});

watch([date, startDate, endDate, reportType], () => {
    fetchReport();
});

// Helper to format duration
const formatDuration = (seconds: number) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `${h}h ${m}m`;
};

const formatShiftDuration = (startedAt: string, endedAt: string | null) => {
    // Calculate duration from actual timestamps to show true elapsed time
    if (!endedAt) return 'Active';
    const start = new Date(startedAt);
    const end = new Date(endedAt);
    const diffMs = end.getTime() - start.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const hours = Math.floor(diffMins / 60);
    const mins = diffMins % 60;
    return `${hours}h ${mins}m`;
};

const formatDateTime = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const currentDuration = ref('');
let durationInterval: any = null;

const startDurationTimer = () => {
    if (durationInterval) clearInterval(durationInterval);
    
    // Timer is only for "Today" view and assumes the shift is currently running
    // The backend might return 'end' as shift end time even if currently running (scheduled end)
    // So we check if date is today. Ideally backend tells us "isActive".
    const isToday = report.value && report.value.shift && report.value.shift.date === new Date().toISOString().split('T')[0];
    
    if (isToday) { 
         const start = new Date(report.value.shift.start).getTime();
         
         const update = () => {
             const now = new Date().getTime();
             const diff = now - start;
             // Only show if positive
             if (diff >= 0) {
                 const h = Math.floor(diff / 3600000);
                 const m = Math.floor((diff % 3600000) / 60000);
                 const s = Math.floor((diff % 60000) / 1000);
                 currentDuration.value = `${h}h ${m}m ${s}s`;
             }
         };
         update();
         durationInterval = setInterval(update, 1000);
    } else {
        currentDuration.value = '';
    }
};

onMounted(() => {
    fetchReport();
    fetchShiftHistory();
});

watch(() => report.value, () => {
    startDurationTimer();
});

onUnmounted(() => {
    if (durationInterval) clearInterval(durationInterval);
});

// Compute max for chart scaling
const maxHourly = (hourly: any) => {
    if (!hourly) return 100;
    const values = Object.values(hourly).map((h: any) => h.good + h.reject);
    return Math.max(...values, 10);
};

// Get bar color based on performance vs target
const getBarColor = (data: { good: number; reject: number }, target: number | null) => {
    const total = data.good + data.reject;
    if (!target) return 'bg-blue-500';
    if (total >= target) return 'bg-emerald-500';
    if (total >= target * 0.8) return 'bg-amber-500'; // Within 80%
    return 'bg-blue-500';
};

// Generate shift hours for empty state (typical 8-hour shift)
const getShiftHours = () => {
    // Try to get from report if available, otherwise default hours
    if (report.value?.shift_start && report.value?.shift_end) {
        const startHour = new Date(report.value.shift_start).getHours();
        const endHour = new Date(report.value.shift_end).getHours();
        const hours = [];
        for (let h = startHour; h !== endHour; h = (h + 1) % 24) {
            hours.push(`${h.toString().padStart(2, '0')}:00`);
            if (hours.length > 12) break; // Safety limit
        }
        return hours;
    }
    // Default: 6AM to 2PM (8 hours)
    return ['06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00'];
};

// Format time for timeline labels
const formatTimeShort = (dateStr: string | null | undefined) => {
    if (!dateStr) return '--:--';
    try {
        const date = new Date(dateStr);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
    } catch {
        return '--:--';
    }
};

// Calculate shift progress percentage
const getShiftProgress = () => {
    if (!report.value?.started_at) return 0;
    
    const start = new Date(report.value.started_at).getTime();
    const now = new Date().getTime();
    
    // If shift has ended, return 100
    if (report.value.ended_at) {
        return 100;
    }
    
    // Estimate end time (8 hours from start if no end time)
    const estimatedEnd = report.value.shift_end 
        ? new Date(report.value.shift_end).getTime()
        : start + (8 * 60 * 60 * 1000);
    
    const totalDuration = estimatedEnd - start;
    const elapsed = now - start;
    
    return Math.min(100, Math.max(0, Math.round((elapsed / totalDuration) * 100)));
};

// Get timeline events with position calculations
const getTimelineEvents = () => {
    const result = { downtime: [] as any[], changeovers: [] as any[] };
    
    if (!report.value?.started_at) return result;
    
    const shiftStart = new Date(report.value.started_at).getTime();
    const shiftEnd = report.value.ended_at 
        ? new Date(report.value.ended_at).getTime()
        : shiftStart + (8 * 60 * 60 * 1000);
    const totalDuration = shiftEnd - shiftStart;
    
    // Process downtime events
    if (report.value?.downtime?.events) {
        report.value.downtime.events.forEach((event: any) => {
            const eventStart = new Date(event.start).getTime();
            const eventDuration = (event.duration || 0) * 1000; // Convert to ms
            
            result.downtime.push({
                reason: event.reason,
                category: event.category,
                duration: event.duration,
                startPercent: Math.min(100, Math.max(0, ((eventStart - shiftStart) / totalDuration) * 100)),
                widthPercent: Math.min(100, Math.max(0.5, (eventDuration / totalDuration) * 100))
            });
        });
    }
    
    // Process product changeovers (if available)
    if (report.value?.changeovers) {
        report.value.changeovers.forEach((change: any) => {
            const changeTime = new Date(change.timestamp).getTime();
            result.changeovers.push({
                from: change.from_product || 'N/A',
                to: change.to_product || 'N/A',
                percent: Math.min(100, Math.max(0, ((changeTime - shiftStart) / totalDuration) * 100))
            });
        });
    }
    
    return result;
};

// Get total good units from hourly production
const getTotalGood = () => {
    if (!report.value?.hourly_production) return 0;
    return Object.values(report.value.hourly_production).reduce((sum: number, h: any) => sum + (h.good || 0), 0);
};

// Get total rejects from hourly production
const getTotalRejects = () => {
    if (!report.value?.hourly_production) return 0;
    return Object.values(report.value.hourly_production).reduce((sum: number, h: any) => sum + (h.reject || 0), 0);
};

const getShiftRuntimeMinutes = (shift: any) => {
    let totalMins = 0;
    
    // Use standard duration if available (fixes issues with manual entry date mismatches)
    if (shift.standard_duration_minutes && shift.standard_duration_minutes > 0) {
        totalMins = shift.standard_duration_minutes;
    } else if (shift.ended_at) {
        const start = new Date(shift.started_at).getTime();
        const end = new Date(shift.ended_at).getTime();
        totalMins = Math.floor((end - start) / 60000);
    } else {
        return 0;
    }

    const downtime = Number(shift.downtime_minutes || 0);
    return Math.max(0, totalMins - downtime);
};

const getShiftRuntime = (shift: any) => {
    return `${getShiftRuntimeMinutes(shift)} min`;
};

const calculateTarget = (shift: any) => {
    if (!shift.ideal_rate) return shift.target_output || 0;
    const runtimeHours = getShiftRuntimeMinutes(shift) / 60;
    return Math.round(runtimeHours * shift.ideal_rate);
};

// Edit functions
const toDatetimeLocal = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const offset = date.getTimezoneOffset() * 60000;
    const local = new Date(date.getTime() - offset);
    return local.toISOString().slice(0, 16);
};

// Add batch_number input to Edit Dialog
// Since we can't easily modify the template which is way down below without strict context,
// we will assume the edit dialog template is similar to create dialog and try to locate it.
// Wait, I need to check where the Edit Dialog template IS. It wasn't in the previous view range.
// I'll skip adding it to Edit Dialog in this chunk and verify its location first.

const openEditDialog = (shift: any) => {
    editingShift.value = shift;
    
    // Map Changeovers with detailed counts
    const changeovers = (shift.changeovers || []).map((co: any) => ({
        to_product_id: co.to_product?.id ?? co.to_product_id,
        changed_at: toDatetimeLocal(co.changed_at),
        batch_number: co.batch_number || '', // Use stored batch if available
        notes: co.notes || '',
        good_count: co.good_count || 0,
        reject_count: co.reject_count || 0,
        material_loss_units: co.material_loss_units || 0
    }));

    // Map Downtime
    const downtimeRecords = (shift.downtimeEvents || []).map((dt: any) => ({
        reason_code_id: dt.reason_code_id,
        start_time: toDatetimeLocal(dt.start_time),
        end_time: toDatetimeLocal(dt.end_time),
        minutes: Math.round(dt.duration_seconds / 60),
        comment: dt.comment || ''
    }));

    // Initial Run Logic
    // If metadata has initial run details, use them. 
    // Otherwise, if we have changeovers, try to infer (Total - Changeovers), else use Total.
    const initialGood = shift.metadata?.initial_run?.good_count ?? Math.max(0, (shift.good_count || 0) - changeovers.reduce((sum: number, co: any) => sum + (co.good_count || 0), 0));
    const initialReject = shift.metadata?.initial_run?.reject_count ?? Math.max(0, (shift.reject_count || 0) - changeovers.reduce((sum: number, co: any) => sum + (co.reject_count || 0), 0));
    const initialLoss = shift.metadata?.initial_run?.material_loss_units ?? Math.max(0, (shift.material_loss_units || 0) - changeovers.reduce((sum: number, co: any) => sum + (co.material_loss_units || 0), 0));
    const initialBatch = shift.metadata?.initial_run?.batch_number ?? shift.batch_number ?? '';

    editForm.value = {
        // Core Identification
        shift_id: shift.shift_id,
        product_id: shift.product_id,
        user_id: shift.user_id,
        started_at: toDatetimeLocal(shift.started_at),
        ended_at: toDatetimeLocal(shift.ended_at),
        
        // Initial Run Specifics
        initial_run: {
            good_count: initialGood,
            reject_count: initialReject,
            material_loss_units: initialLoss,
            batch_number: initialBatch
        },

        // Legacy/Total fields (read-only or calculated in backend)
        good_count: shift.good_count || 0, 
        reject_count: shift.reject_count || 0,
        material_loss_units: shift.material_loss_units || 0,
        batch_number: shift.batch_number || '',

        downtime_minutes: shift.downtime_minutes || 0,
        comment: '',
        changeovers: changeovers,
        downtime_records: downtimeRecords,
        product_counts: shift.product_counts || []
    };
    editDialogOpen.value = true;
};

const addChangeover = () => {
    editForm.value.changeovers.push({
        to_product_id: null,
        changed_at: editForm.value.started_at, // Default to start
        batch_number: '',
        notes: ''
    });
};

const removeChangeover = (index: number) => {
    editForm.value.changeovers.splice(index, 1);
};

const addDowntimeRecord = () => {
    editForm.value.downtime_records.push({
        reason_code_id: null,
        start_time: editForm.value.started_at,
        end_time: editForm.value.ended_at,
        minutes: 0,
        comment: ''
    });
};

const removeDowntimeRecord = (index: number) => {
    editForm.value.downtime_records.splice(index, 1);
};

const submitEdit = async () => {
    if (!editingShift.value) return;
    
    editLoading.value = true;
    try {
        const payload = { ...editForm.value };
        if (payload.started_at) payload.started_at = new Date(payload.started_at).toISOString();
        if (payload.ended_at) payload.ended_at = new Date(payload.ended_at).toISOString();
        
        const res = await axios.put(`/admin/production-shifts/shift/${editingShift.value.id}`, payload);
        
        if (res.data.success) {
            // Refresh shift history
            await fetchShiftHistory();
            editDialogOpen.value = false;
            
            // Show success message (you can use a toast here)
            console.log('Shift updated successfully');
        }
    } catch (e: any) {
        console.error('Failed to update shift:', e);
        alert(e.response?.data?.error || 'Failed to update shift report.');
    } finally {
        editLoading.value = false;
    }
};

const viewHistory = async (shift: any) => {
    viewingHistory.value = shift;
    try {
        const res = await axios.get(`/admin/production-shifts/shift/${shift.id}/edit-history`);
        editHistory.value = res.data.edit_history || [];
        historyDialogOpen.value = true;
    } catch (e: any) {
        console.error('Failed to load edit history:', e);
        alert('Failed to load edit history');
    }
};

const formatHistoryDateTime = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getRunDuration = (shift: any, idx: number) => {
    if (!shift.changeovers || !shift.changeovers[idx]) return 0;
    const end = new Date(shift.changeovers[idx].changed_at).getTime();
    const start = new Date(idx === 0 ? shift.started_at : shift.changeovers[idx - 1].changed_at).getTime();
    return Math.max(0, Math.round((end - start) / 60000));
};

const getRunStartTime = (shift: any, idx: number) => {
    return idx === 0 ? shift.started_at : shift.changeovers[idx - 1].changed_at;
};

const getShiftRuns = (shift: any) => {
    if (!shift) return [];
    
    // 1. Start with initial product run
    const runs = [{
        id: 'initial',
        sequence: 1,
        product: { id: shift.product_id, name: shift.product_name },
        batch_number: shift.batch_number || shift.metadata?.initial_run?.batch_number || '',
        start: shift.started_at,
        end: shift.changeovers && shift.changeovers.length > 0 
            ? shift.changeovers[0].changed_at 
            : (shift.ended_at || new Date().toISOString()),
        recorded_by: null
    }];

    // 2. Add changeover runs
    if (shift.changeovers && shift.changeovers.length > 0) {
        shift.changeovers.forEach((co: any, idx: number) => {
            const nextChange = shift.changeovers[idx + 1];
            runs.push({
                id: co.id,
                sequence: runs.length + 1,
                product: co.to_product,
                batch_number: co.batch_number || '',
                start: co.changed_at,
                end: nextChange 
                    ? nextChange.changed_at 
                    : (shift.ended_at || new Date().toISOString()),
                recorded_by: co.recorded_by
            });
        });
    }

    // 3. Enrich with Duration, Target, and Ideal Rate
    return runs.map(run => {
        const start = new Date(run.start).getTime();
        const end = new Date(run.end).getTime();
        const durationMins = Math.max(0, Math.floor((end - start) / 60000));
        
        // Lookup target info from product_counts
        let target = 0;
        let idealRate = 0;
        
        if (shift.product_counts) {
            // Find finding by product_id
            // Note: If a product is run multiple times, the target in product_counts
            // is the aggregate. Ideally we'd need segment-specific targets stored.
            // But currently the backend stores aggregate per product in product_counts.
            // For now, we show the aggregate target for that product or if we stored arrays we'd use that.
            // WAIT - In my backend implementation I aggregated the target per product ID.
            // So if Product A is run twice, 'target_output' is the sum.
            // This might mean showing the TOTAL target for Product A on both rows?
            // User asked for "indicate each ideal rate and target base on the calculation".
            // If I show the total target on both lines, it might be confusing if they sum it up mentally.
            // However, without segment-specific storage refactoring, this is what we have.
            // Let's rely on Ideal Rate being correct per product. 
            // Better: We can estimate the segment target here: Duration * Ideal Rate.
            // That's actually more accurate for the visual "Run" breakdown!
            
            const pc = shift.product_counts.find((p: any) => p.product_id === run.product.id);
            if (pc) {
                idealRate = pc.ideal_rate || 0;
            }
        }
        
        // Calculate segment target dynamically for display consistency
        // (This avoids the confusion of showing the Total Shift Target for that product on a partial run)
        if (idealRate > 0) {
            target = Math.floor((durationMins / 60) * idealRate);
        }

        return {
            ...run,
            duration: durationMins,
            target,
            idealRate
        };
    });
};

</script>

<template>
    <div class="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">
        
        <!-- Controls -->
        <div class="flex flex-col gap-3 mb-4 p-3 bg-muted/20 border rounded-lg">
             <div class="flex items-center gap-2 flex-wrap">
                 <Badge 
                    variant="outline" 
                    class="cursor-pointer hover:bg-secondary transition-colors"
                    :class="{ 'bg-primary text-primary-foreground border-primary': reportType === 'daily' && date === new Date().toISOString().split('T')[0] }"
                    @click="setRange('today')">
                    Today
                </Badge>
                <Badge 
                    variant="outline" 
                    class="cursor-pointer hover:bg-secondary transition-colors"
                     :class="{ 'bg-primary text-primary-foreground border-primary': reportType === 'daily' && date !== new Date().toISOString().split('T')[0] && date === new Date(Date.now() - 86400000).toISOString().split('T')[0] }"
                    @click="setRange('yesterday')">
                    Yesterday
                </Badge>
                <Badge 
                    variant="outline" 
                    class="cursor-pointer hover:bg-secondary transition-colors"
                    :class="{ 'bg-primary text-primary-foreground border-primary': reportType === 'range' && new Date(startDate).getDate() === new Date().getDate() - new Date().getDay() + 1 }" 
                    @click="setRange('week')">
                    This Week
                </Badge>
                 <Badge 
                    variant="outline" 
                    class="cursor-pointer hover:bg-secondary transition-colors"
                    :class="{ 'bg-primary text-primary-foreground border-primary': reportType === 'range' && new Date(startDate).getDate() === 1 && new Date(startDate).getMonth() === new Date().getMonth() }"
                    @click="setRange('month')">
                    This Month
                </Badge>
             </div>
             
             <div class="flex items-center gap-4 flex-wrap">
                 <!-- Daily Mode -->
                <div v-if="reportType === 'daily'" class="flex items-center gap-2">
                     <Label class="text-xs font-medium whitespace-nowrap">Single Date:</Label>
                     <Input type="date" v-model="date" class="h-8 w-[140px] text-xs" />
                </div>
                
                <!-- Range Mode -->
                <div v-else class="flex items-center gap-2">
                    <Label class="text-xs font-medium whitespace-nowrap">From:</Label>
                    <Input type="date" v-model="startDate" class="h-8 w-[130px] text-xs" />
                    <Label class="text-xs font-medium whitespace-nowrap">To:</Label>
                    <Input type="date" v-model="endDate" class="h-8 w-[130px] text-xs" />
                </div>
                
                 <div class="ml-auto">
                    <Button variant="outline" size="sm" @click="fetchReport" :disabled="loading" class="gap-1.5 h-8">
                    <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" />
                    <span class="sr-only sm:not-sr-only sm:inline-block">Refresh</span>
                </Button>

            </div>
             </div>
        </div>

        <div v-if="loading" class="p-8 text-center text-muted-foreground flex flex-col items-center justify-center min-h-[200px]">
            <Loader2 class="h-8 w-8 animate-spin mb-2" />
            <p>Loading {{ reportType === 'range' ? 'consolidated ' : '' }}shift report...</p>
        </div>

        <div v-else-if="error" class="p-8 text-center border rounded-lg bg-muted/20 min-h-[200px] flex flex-col items-center justify-center">
            <FileWarning class="h-10 w-10 text-muted-foreground mb-3 opacity-50" />
            <h3 class="font-semibold text-lg">No Report Data</h3>
            <p class="text-sm text-muted-foreground max-w-xs mx-auto mb-4">{{ error }}</p>
            <Button size="sm" variant="outline" @click="setRange('today')" v-if="date !== new Date().toISOString().split('T')[0]">
                Go to Today
            </Button>
        </div>

        <div v-else-if="report" class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b pb-4">
                <div>
                    <h3 class="text-2xl font-bold tracking-tight">
                        {{ reportType === 'range' ? 'Period Summary' : 'Shift Report' }}
                    </h3>
                    <div class="flex items-center gap-2 mt-1" v-if="reportType === 'daily'">
                        <Badge variant="outline" class="text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-700">
                            {{ report.shift.name }}
                        </Badge>
                        <Badge variant="secondary" class="text-[10px] uppercase font-bold tracking-wider" :class="report.shift.type === 'night' ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300'">
                             {{ report.shift.type }}
                        </Badge>
                        <span class="text-muted-foreground text-sm">{{ report.shift.date }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1" v-else>
                         <Badge variant="outline">{{ startDate }} <span class="mx-1">to</span> {{ endDate }}</Badge>
                         <span class="text-xs text-muted-foreground">Consolidated Data</span>
                    </div>
                </div>
                <div class="text-right">
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-muted-foreground uppercase">Total Output</CardTitle>
                    </CardHeader>
                    <CardContent>
                         <div class="text-2xl font-bold">{{ report.production.total }}</div>
                         <p class="text-xs text-muted-foreground">Units Produced</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-muted-foreground uppercase">Good Count</CardTitle>
                    </CardHeader>
                    <CardContent>
                         <div class="text-2xl font-bold text-green-600">{{ report.production.good }}</div>
                         <p class="text-xs text-muted-foreground">Qualified Units</p>
                    </CardContent>
                </Card>
                 <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-muted-foreground uppercase">Rejects</CardTitle>
                    </CardHeader>
                    <CardContent>
                         <div class="text-2xl font-bold text-red-500">{{ report.production.reject }}</div>
                         <p class="text-xs text-muted-foreground">Failed Units</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-muted-foreground uppercase">Total Downtime</CardTitle>
                    </CardHeader>
                    <CardContent>
                         <div class="text-2xl font-bold text-amber-600">{{ formatDuration(report.downtime.total_seconds) }}</div>
                         <p class="text-xs text-muted-foreground">{{ report.downtime.count }} Stops</p>
                    </CardContent>
                </Card>
                <Card> <!-- Planned -->
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-muted-foreground uppercase">Planned Stop</CardTitle>
                    </CardHeader>
                    <CardContent>
                         <div class="text-2xl font-bold text-blue-600">{{ formatDuration(report.downtime.planned_seconds) }}</div>
                         <p class="text-[10px] text-muted-foreground whitespace-nowrap truncate" title="Maintenance/Break">Maintenance/Break</p>
                    </CardContent>
                </Card>
                <Card> <!-- Unplanned -->
                    <CardHeader class="pb-2">
                        <CardTitle class="text-[10px] font-medium text-muted-foreground uppercase whitespace-nowrap">Unplanned Stop</CardTitle>
                    </CardHeader>
                    <CardContent>
                         <div class="text-2xl font-bold text-orange-600">{{ formatDuration(report.downtime.unplanned_seconds) }}</div>
                         <p class="text-[10px] text-muted-foreground whitespace-nowrap truncate" title="Failures/Jams">Failures/Jams</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid md:grid-cols-2 gap-6 h-full">
                <!-- Shift Comparison -->
                <Card class="flex flex-col h-full">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">Shift Comparison</CardTitle>
                        <CardDescription class="text-xs">Performance comparison of shifts on this date.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-4 pt-0">
                        <!-- Chart Section -->
                        <div v-if="currentDateShifts.length > 0" class="h-[200px] w-full mb-4 mt-2">
                            <Bar :data="comparisonChartData" :options="chartOptions" />
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/30">
                                        <th class="h-9 px-4 text-left font-medium text-muted-foreground w-[120px]">Shift</th>
                                        <th class="h-9 px-4 text-center font-medium text-muted-foreground">Output</th>
                                        <th class="h-9 px-4 text-center font-medium text-muted-foreground">Good</th>
                                        <th class="h-9 px-4 text-center font-medium text-muted-foreground">Reject</th>
                                        <th class="h-9 px-4 text-right font-medium text-muted-foreground">Downtime</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="currentDateShifts.length === 0">
                                        <td colspan="5" class="p-8 text-center text-muted-foreground italic text-xs">No shifts found for this date.</td>
                                    </tr>
                                    <tr v-for="shift in currentDateShifts" :key="shift.id" class="border-b last:border-0 hover:bg-muted/10 transition-colors">
                                        <td class="p-3 font-medium">
                                            <div class="truncate max-w-[110px]" :title="shift.shiftName">{{ shift.shiftName || 'Shift ' + shift.id }}</div>
                                            <div class="text-[10px] text-muted-foreground font-normal">{{ formatTimeShort(shift.started_at) }}</div>
                                        </td>
                                        <td class="p-3 text-center font-mono">{{ shift.total_count }}</td>
                                        <td class="p-3 text-center font-mono text-emerald-600">{{ shift.good_count }}</td>
                                        <td class="p-3 text-center font-mono text-red-500">{{ shift.reject_count }}</td>
                                        <td class="p-3 text-right font-mono text-orange-600">{{ shift.downtime_minutes }}m</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Downtime Log -->
                 <Card class="flex flex-col h-full">
                    <CardHeader>
                        <CardTitle>Downtime Log</CardTitle>
                        <CardDescription>Recent stops during this shift.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0 flex-1 overflow-hidden">
                        <ScrollArea class="h-[300px]">
                            <div class="space-y-1 p-4">
                                <div v-if="report.downtime.events.length === 0" class="text-sm text-center text-muted-foreground italic py-8">
                                    No downtime recorded.
                                </div>
                                <div v-for="event in report.downtime.events" :key="event.id" 
                                     class="flex items-start justify-between p-3 rounded-lg border bg-muted/20 text-sm">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-foreground">{{ event.reason }}</span>
                                            <Badge variant="outline" class="text-[10px] h-4 px-1" :class="event.category === 'planned' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-700' : 'bg-orange-50 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-700'">{{ event.category }}</Badge>
                                        </div>
                                        <span class="text-xs text-muted-foreground">{{ event.start.split(' ')[1] }} &bull; {{ event.comment || 'No comment' }}</span>
                                    </div>
                                    <Badge variant="outline" class="font-mono whitespace-nowrap bg-white">{{ formatDuration(event.duration) }}</Badge>
                                </div>
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>
            </div>
        </div>
        
        <!-- Shift History Section -->
        <Card class="mt-6">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Calendar class="h-5 w-5" />
                    Shift History
                </CardTitle>
                <CardDescription>Completed shifts for this machine.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="shiftHistory.length === 0" class="p-8 text-center text-muted-foreground italic">
                    No completed shifts recorded yet.
                </div>
                <div v-else class="flex flex-col gap-3 sm:gap-4 p-2 sm:p-4 bg-muted/5">
                    <div v-for="shift in shiftHistory" :key="shift.id" 
                         class="flex flex-col gap-3 sm:gap-4 p-3 sm:p-5 rounded-lg sm:rounded-xl border bg-card text-card-foreground shadow-sm transition-all hover:shadow-md hover:border-primary/20">
                        
                        <!-- Top Row: User, type, status -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center gap-3 sm:gap-4 min-w-0 flex-1">
                                <div class="h-10 w-10 flex-shrink-0 rounded-full bg-primary/10 flex items-center justify-center">
                                    <User class="h-5 w-5 text-primary" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-base sm:text-lg text-foreground truncate">{{ shift.user_name }}</p>
                                    <div class="flex items-center gap-1.5 sm:gap-2 text-sm text-muted-foreground mt-0.5 flex-wrap">
                                        <Badge v-if="shift.user_group" variant="secondary" class="text-xs px-1.5 sm:px-2 py-0.5">
                                            {{ shift.user_group }}
                                        </Badge>
                                        <span v-if="shift.shift_name" class="font-medium text-foreground text-xs sm:text-sm">{{ shift.shift_name }}</span>
                                       <Badge v-if="shift.shift_type" variant="outline" class="text-[10px] sm:text-xs uppercase px-1.5 sm:px-2 py-0.5" :class="shift.shift_type === 'night' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-700' : 'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-700'">
                                            {{ shift.shift_type }}
                                        </Badge>
                                        <Badge v-if="shift.batch_number" variant="outline" class="text-[10px] sm:text-xs px-1.5 sm:px-2 py-0.5 font-mono">
                                            Batch: {{ shift.batch_number }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between sm:justify-end gap-3 flex-wrap">
                                <div class="text-left sm:text-right">
                                     <div class="flex items-center sm:justify-end gap-1 sm:gap-1.5 text-xs sm:text-sm text-muted-foreground">
                                        <Calendar class="h-3 sm:h-3.5 w-3 sm:w-3.5 flex-shrink-0" />
                                        <span class="text-xs sm:text-sm">{{ formatDateTime(shift.started_at) }}</span>
                                    </div>
                                    <div class="flex items-center sm:justify-end gap-1 sm:gap-1.5 text-xs sm:text-sm font-mono mt-1 text-foreground/80">
                                        <Clock class="h-3 sm:h-3.5 w-3 sm:w-3.5 flex-shrink-0" />
                                        <span class="text-xs sm:text-sm">{{ formatShiftDuration(shift.started_at, shift.ended_at) }}</span>
                                    </div>
                                    <!-- Edited indicator -->
                                    <div v-if="shift.edited_at" class="flex items-center sm:justify-end gap-1 text-[10px] sm:text-xs text-blue-600 dark:text-blue-400 mt-1">
                                        <Edit class="h-2.5 sm:h-3 w-2.5 sm:w-3 flex-shrink-0" />
                                        <span class="truncate max-w-[150px] sm:max-w-none">Edited by {{ shift.edited_by?.name }}</span>
                                    </div>
                                </div>
                                <Badge :variant="shift.status === 'completed' ? 'default' : shift.status === 'active' ? 'outline' : 'destructive'" class="capitalize text-xs px-2.5 py-0.5 flex-shrink-0">
                                    {{ shift.status }}
                                </Badge>
                                <!-- Action Buttons -->
                                <div v-if="shift.status === 'completed'" class="flex gap-1 flex-shrink-0">
                                    <Button size="sm" variant="ghost" @click="openEditDialog(shift)" class="h-7 sm:h-8 w-7 sm:w-8 p-0">
                                        <Edit class="h-3 sm:h-3.5 w-3 sm:w-3.5" />
                                    </Button>
                                    <Button v-if="shift.edited_at" size="sm" variant="ghost" @click="viewHistory(shift)" class="h-7 sm:h-8 w-7 sm:w-8 p-0">
                                        <History class="h-3 sm:h-3.5 w-3 sm:w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Row: Stats -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-9 gap-3 sm:gap-4 md:gap-6 text-sm border-t pt-4 mt-1">
                            <div class="col-span-2 sm:col-span-3 md:col-span-1">
                                 <span class="text-muted-foreground block text-[10px] sm:text-xs font-semibold uppercase tracking-wider mb-1">Product</span>
                                 <span class="font-medium truncate block text-sm sm:text-base" :title="shift.product_name">{{ shift.product_name }}</span>
                            </div>
                            <div>
                                 <span class="text-muted-foreground block text-xs font-semibold uppercase tracking-wider mb-1">Target</span>
                                 <div class="flex flex-col">
                                     <span class="font-mono font-bold text-lg text-blue-600 dark:text-blue-400">{{ calculateTarget(shift) }}</span>
                                     <span class="text-[10px] text-muted-foreground font-medium" v-if="shift.ideal_rate && shift.ideal_rate > 0">
                                        @ {{ shift.ideal_rate }}/hr
                                     </span>
                                     <span class="text-[10px] text-red-500 font-medium flex items-center gap-1" v-else>
                                        <AlertCircle class="h-3 w-3" /> No Rate
                                     </span>
                                 </div>
                            </div>
                            <div>
                                 <span class="text-muted-foreground block text-xs font-semibold uppercase tracking-wider mb-1">Total Output</span>
                                 <span class="font-mono font-bold text-lg">{{ shift.total_output || 0 }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground block text-xs font-semibold uppercase tracking-wider mb-1">Good</span>
                                <span class="font-mono text-green-600 font-bold text-lg">{{ shift.good_count || 0 }}</span>
                            </div>
                            <div>
                                <span class=" text-muted-foreground block text-xs font-semibold uppercase tracking-wider mb-1">Reject</span>
                                <span class="font-mono text-red-500 font-bold text-lg">{{ shift.reject_count || 0 }}</span>
                            </div>
                            <div v-if="shift.material_loss_units !== undefined && shift.material_loss_units > 0" class="bg-orange-50/50 dark:bg-orange-900/20 -mx-1 px-1 rounded">
                                <span class="text-muted-foreground block text-xs font-semibold uppercase tracking-wider mb-1 flex items-center gap-1">
                                    <PackageX class="h-3 w-3" />
                                    Mat. Loss
                                </span>
                                <div class="flex flex-col">
                                    <span class="font-mono text-orange-600 font-bold text-lg">{{ shift.material_loss_units || 0 }}</span>
                                    <span class="text-[10px] text-muted-foreground font-medium" v-if="shift.material_loss_cost">
                                        ${{ shift.material_loss_cost.toFixed(2) }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="shift.quality_score !== undefined && shift.quality_score !== null" class="bg-purple-50/50 dark:bg-purple-900/20 -mx-1 px-1 rounded">
                                <span class="text-muted-foreground block text-xs font-semibold uppercase tracking-wider mb-1">Quality</span>
                                <span class="font-mono text-purple-600 dark:text-purple-400 font-bold text-lg">{{ shift.quality_score.toFixed(1) }}%</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground block text-xs font-semibold uppercase tracking-wider mb-1">Downtime</span>
                                <div class="flex flex-col">
                                    <span class="font-mono text-amber-600 font-bold text-lg">{{ shift.downtime_minutes || 0 }} min</span>
                                    <span class="text-[10px] text-muted-foreground font-medium truncate max-w-[120px]" :title="shift.downtime_reason" v-if="shift.downtime_reason">
                                        {{ shift.downtime_reason }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <span class="text-muted-foreground block text-xs font-semibold uppercase tracking-wider mb-1">Runtime</span>
                                <div class="flex flex-col">
                                    <span class="font-mono text-cyan-600 dark:text-cyan-400 font-bold text-lg">{{ getShiftRuntime(shift) }}</span>
                                    <span class="text-[10px] text-muted-foreground font-medium" v-if="shift.standard_duration_minutes">
                                        / {{ Math.round(shift.standard_duration_minutes / 60 * 10) / 10 }}h Shift
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product Runs Breakdown -->
                        <div v-if="shift.changeovers && shift.changeovers.length > 0" class="mt-4 pt-4 border-t">
                            <div class="flex items-center gap-2 mb-3">
                                <Package class="h-4 w-4 text-purple-600" />
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Product Runs & Changeovers ({{ shift.changeovers.length + 1 }})</span>
                            </div>
                            <div class="space-y-2">
                                <div v-for="run in getShiftRuns(shift)" :key="run.sequence" 
                                     class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 p-3 rounded-lg border text-sm transition-colors"
                                     :class="run.sequence === 1 ? 'bg-muted/30 border-muted' : 'bg-purple-50/50 dark:bg-purple-900/10 border-purple-200/50 dark:border-purple-800/30'">
                                    
                                    <!-- Sequence & Product -->
                                    <div class="flex items-center gap-3 min-w-[180px]">
                                        <Badge variant="outline" class="h-6 w-6 rounded-full p-0 flex items-center justify-center border-2 font-mono text-xs">
                                            {{ run.sequence }}
                                        </Badge>
                                        <div class="flex flex-col">
                                            <div class="font-bold text-foreground truncate" :title="run.product.name">
                                                {{ run.product.name }}
                                            </div>
                                            <div v-if="run.batch_number" class="text-[10px] text-muted-foreground font-mono">
                                                Batch: {{ run.batch_number }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Time & Duration -->
                                    <div class="flex items-center gap-2 text-muted-foreground">
                                        <Clock class="h-3.5 w-3.5" />
                                        <div class="flex items-center gap-1.5 whitespace-nowrap text-xs sm:text-sm">
                                            <span class="font-medium font-mono text-foreground">{{ formatTimeShort(run.start) }}</span>
                                            <ArrowRight class="h-3 w-3 opacity-50" />
                                            <span class="font-medium font-mono text-foreground">{{ formatTimeShort(run.end) }}</span>
                                        </div>
                                        <Badge variant="secondary" class="ml-1 text-xs font-mono h-5 px-1.5">
                                            {{ run.duration }} min
                                        </Badge>
                                    </div>

                                    <!-- Targets -->
                                    <div class="mt-1 sm:mt-0 sm:ml-auto flex items-center gap-4 text-xs">
                                        <div v-if="run.target" class="flex flex-col sm:items-end">
                                            <span class="text-[10px] uppercase text-muted-foreground font-semibold">Target</span>
                                            <span class="font-mono font-bold text-blue-600 dark:text-blue-400">{{ run.target }}</span>
                                        </div>
                                        <div v-if="run.idealRate" class="flex flex-col sm:items-end">
                                             <span class="text-[10px] uppercase text-muted-foreground font-semibold">Ideal Rate</span>
                                             <span class="font-mono font-medium">{{ run.idealRate }}/hr</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Recorder (if changeover) -->
                                    <div v-if="run.recorded_by" class="hidden sm:block text-[10px] text-muted-foreground ml-2">
                                        by {{ run.recorded_by }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
        

        
        <!-- Edit Shift Dialog -->
        <Dialog v-model:open="editDialogOpen">
            <DialogContent class="sm:max-w-[1000px] max-h-[90vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Edit Shift Report</DialogTitle>
                    <DialogDescription>
                        Update production counts for this shift. Changes will be tracked.
                    </DialogDescription>
                </DialogHeader>
                
                <div v-if="editingShift" class="space-y-4 py-4 flex-1 overflow-y-auto max-h-[70vh] pr-2">
                    <div class="space-y-6">
                        <!-- Section 1: Core Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Card>
                                <CardHeader class="py-3 px-4 bg-muted/40">
                                    <CardTitle class="text-sm">Shift Details</CardTitle>
                                </CardHeader>
                                <CardContent class="p-4 space-y-3">
                                    <div class="space-y-1">
                                        <Label>Shift Type</Label>
                                        <select v-model="editForm.shift_id" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50">
                                            <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                                                {{ shift.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-1">
                                        <Label>Operator</Label>
                                        <select v-model="editForm.user_id" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50">
                                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                            <div class="space-y-1">
                                            <Label>Start</Label>
                                            <Input type="datetime-local" v-model="editForm.started_at" class="h-9" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label>End</Label>
                                            <Input type="datetime-local" v-model="editForm.ended_at" class="h-9" />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>


                            <Card class="flex flex-col h-full">
                                <CardHeader class="py-3 px-4 bg-muted/40 flex flex-row items-center justify-between space-y-0">
                                    <CardTitle class="text-sm flex items-center gap-2">
                                        <PauseCircle class="h-4 w-4" /> Downtime Events
                                    </CardTitle>
                                    <Button size="sm" variant="ghost" class="h-8 px-2" @click="addDowntimeRecord">
                                        <Plus class="h-3 w-3 mr-1" /> Add
                                    </Button>
                                </CardHeader>
                                <CardContent class="p-4 flex-1 flex flex-col gap-4">
                                     <div class="border rounded-md divide-y bg-card flex-1 overflow-y-auto max-h-[300px]">
                                        <div v-if="editForm.downtime_records.length === 0" class="p-4 text-center text-sm text-muted-foreground">
                                            No downtime events logged.
                                        </div>
                                        <div v-for="(dt, idx) in editForm.downtime_records" :key="idx" class="p-3 relative group">
                                                <Button variant="ghost" size="icon" @click="removeDowntimeRecord(idx)" class="absolute top-2 right-2 h-6 w-6 text-muted-foreground hover:text-destructive opacity-0 group-hover:opacity-100 transition-opacity">
                                                <X class="h-3 w-3" />
                                            </Button>
                                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                                                <div class="md:col-span-12 space-y-1">
                                                    <Label class="text-[10px] uppercase">Reason</Label>
                                                    <select v-model="dt.reason_code_id" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                                        <option :value="null">Select Reason...</option>
                                                        <option v-for="rc in reasonCodes" :key="rc.id" :value="rc.id">
                                                            {{ rc.category?.name ? `${rc.category.name} - ` : '' }}{{ rc.name }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="md:col-span-6 space-y-1">
                                                    <Label class="text-[10px] uppercase">Start</Label>
                                                    <Input type="datetime-local" v-model="dt.start_time" class="h-9" />
                                                </div>
                                                <div class="md:col-span-6 space-y-1">
                                                    <Label class="text-[10px] uppercase">End</Label>
                                                    <Input type="datetime-local" v-model="dt.end_time" class="h-9" />
                                                </div>
                                                <div class="md:col-span-12 flex items-center justify-between pt-1">
                                                     <div class="flex items-center gap-2">
                                                         <span class="text-[10px] uppercase text-muted-foreground">Duration:</span>
                                                         <span class="text-xs font-mono text-amber-600 bg-amber-50 px-2 py-1 rounded">
                                                            {{ dt.start_time && dt.end_time ? Math.round((new Date(dt.end_time).getTime() - new Date(dt.start_time).getTime()) / 60000) : 0 }} min
                                                        </span>
                                                     </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 mt-auto pt-2 border-t">
                                            <Label class="text-xs text-muted-foreground">Manual Override (Total):</Label>
                                            <Input type="number" v-model.number="editForm.downtime_minutes" class="h-8 w-24 text-xs" />
                                            <span class="text-xs text-muted-foreground">min</span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Section 2: Product Runs / Changeovers -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label class="text-base font-semibold flex items-center gap-2">
                                    <Package class="h-4 w-4" /> Product Runs
                                </Label>
                                <Button size="sm" variant="outline" @click="addChangeover">
                                    <Plus class="h-3 w-3 mr-1" /> Changeover
                                </Button>
                            </div>
                            <div class="border rounded-md divide-y bg-card">
                                <!-- Initial Run -->
                                <div class="p-4 bg-muted/20 space-y-3">
                                    <div class="flex items-center gap-2">
                                        <Badge variant="outline" class="h-5 w-5 rounded-full p-0 flex items-center justify-center text-[10px]">1</Badge>
                                        <span class="text-xs font-semibold text-muted-foreground uppercase">Initial Product</span>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-1">
                                            <Label class="text-[10px] uppercase">Product</Label>
                                            <select v-model="editForm.product_id" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                                <option v-for="product in products" :key="product.id" :value="product.id">
                                                    {{ product.name }}{{ product.sku ? ` (${product.sku})` : '' }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-[10px] uppercase">Batch #</Label>
                                            <Input v-model="editForm.initial_run.batch_number" placeholder="Initial Batch #" class="h-9" />
                                        </div>
                                    </div>

                                    <!-- Production Counts for Initial Run -->
                                    <div class="grid grid-cols-3 gap-3 pt-2 border-t border-dashed border-gray-200 dark:border-gray-700">
                                        <div class="space-y-1">
                                            <Label class="text-[10px] uppercase text-green-600">Good</Label>
                                            <Input type="number" v-model.number="editForm.initial_run.good_count" class="h-8" min="0" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-[10px] uppercase text-red-600">Reject</Label>
                                            <Input type="number" v-model.number="editForm.initial_run.reject_count" class="h-8" min="0" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-[10px] uppercase">Loss (Units)</Label>
                                            <Input type="number" v-model.number="editForm.initial_run.material_loss_units" class="h-8" min="0" step="0.01" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Changeovers -->
                                    <div v-for="(co, idx) in editForm.changeovers" :key="idx" class="p-4 relative group space-y-3 bg-muted/5">
                                        <Button variant="ghost" size="icon" @click="removeChangeover(idx)" class="absolute top-2 right-2 h-6 w-6 text-muted-foreground hover:text-destructive opacity-0 group-hover:opacity-100 transition-opacity">
                                            <X class="h-3 w-3" />
                                        </Button>
                                        <div class="flex items-center gap-2">
                                            <Badge variant="outline" class="h-5 w-5 rounded-full p-0 flex items-center justify-center text-[10px]">{{ idx + 2 }}</Badge>
                                            <span class="text-xs font-semibold text-muted-foreground uppercase">Changeover</span>
                                        </div>
                                        
                                        <!-- Core Changeover Info -->
                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                                            <div class="md:col-span-4 space-y-1">
                                                <Label class="text-[10px] uppercase">To Product</Label>
                                                <select v-model="co.to_product_id" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                                    <option v-for="product in products" :key="product.id" :value="product.id">
                                                        {{ product.name }}{{ product.sku ? ` (${product.sku})` : '' }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="md:col-span-3 space-y-1">
                                                <Label class="text-[10px] uppercase">Changed At</Label>
                                                <Input type="datetime-local" v-model="co.changed_at" class="h-9" />
                                            </div>
                                            <div class="md:col-span-3 space-y-1">
                                                <Label class="text-[10px] uppercase">Batch #</Label>
                                                <Input v-model="co.batch_number" placeholder="Batch #" class="h-9" />
                                            </div>
                                            <div class="md:col-span-2 space-y-1">
                                                <Label class="text-[10px] uppercase">Notes</Label>
                                                <Input v-model="co.notes" placeholder="Notes" class="h-9" />
                                            </div>
                                        </div>
                                        
                                        <!-- Production Counts for Changeover -->
                                        <div class="grid grid-cols-3 gap-3 pt-2 border-t border-dashed border-gray-200 dark:border-gray-700">
                                            <div class="space-y-1">
                                                <Label class="text-[10px] uppercase text-green-600">Good</Label>
                                                <Input type="number" v-model.number="co.good_count" class="h-8" min="0" />
                                            </div>
                                            <div class="space-y-1">
                                                <Label class="text-[10px] uppercase text-red-600">Reject</Label>
                                                <Input type="number" v-model.number="co.reject_count" class="h-8" min="0" />
                                            </div>
                                            <div class="space-y-1">
                                                <Label class="text-[10px] uppercase">Loss (Units)</Label>
                                                <Input type="number" v-model.number="co.material_loss_units" class="h-8" min="0" step="0.01" />
                                            </div>
                                        </div>
                                    </div>
                            </div>
                        </div>


                        
                        <div class="space-y-1">
                            <Label>Edit Comment</Label>
                            <Textarea v-model="editForm.comment" placeholder="Explain why this report is being edited..." rows="2" />
                        </div>
                    </div>
                </div>
                
                <DialogFooter>
                    <Button variant="outline" @click="editDialogOpen = false" :disabled="editLoading">
                        Cancel
                    </Button>
                    <Button @click="submitEdit" :disabled="editLoading">
                        <Loader2 v-if="editLoading" class="h-4 w-4 mr-2 animate-spin" />
                        Save Changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        
        <!-- Edit History Dialog -->
        <Dialog v-model:open="historyDialogOpen">
            <DialogContent class="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>Edit History</DialogTitle>
                    <DialogDescription>
                        All changes made to this shift report
                    </DialogDescription>
                </DialogHeader>
                
                <ScrollArea class="max-h-[400px] pr-4">
                    <div v-if="editHistory.length === 0" class="text-center text-muted-foreground py-8">
                        No edit history available
                    </div>
                    
                    <div v-else class="space-y-4">
                        <div v-for="(entry, index) in editHistory" :key="index" 
                             class="border rounded-lg p-4 space-y-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="font-semibold text-sm">{{ entry.edited_by_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ formatHistoryDateTime(entry.edited_at) }}</p>
                                </div>
                                <Badge variant="outline" class="text-xs">Edit #{{ editHistory.length - index }}</Badge>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div class="space-y-1">
                                    <p class="text-xs font-semibold text-muted-foreground uppercase">Before</p>
                                    <p>Good: {{ entry.changes.old.good_count }}</p>
                                    <p>Reject: {{ entry.changes.old.reject_count }}</p>
                                    <p class="font-semibold">Total: {{ entry.changes.old.good_count + entry.changes.old.reject_count }}</p>
                                </div>
                                
                                <div class="space-y-1">
                                    <p class="text-xs font-semibold text-muted-foreground uppercase">After</p>
                                    <p class="text-green-600">Good: {{ entry.changes.new.good_count }}</p>
                                    <p class="text-red-500">Reject: {{ entry.changes.new.reject_count }}</p>
                                    <p class="font-semibold">Total: {{ entry.changes.new.good_count + entry.changes.new.reject_count }}</p>
                                </div>
                            </div>
                            
                            <div v-if="entry.comment" class="pt-2 border-t">
                                <p class="text-xs text-muted-foreground font-semibold mb-1">Comment:</p>
                                <p class="text-sm italic">{{ entry.comment }}</p>
                            </div>
                        </div>
                    </div>
                </ScrollArea>
                
                <DialogFooter>
                    <Button variant="outline" @click="historyDialogOpen = false">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>


