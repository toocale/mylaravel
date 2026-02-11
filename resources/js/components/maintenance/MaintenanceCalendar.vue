<script setup lang="ts">
import { ref, onMounted } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useToast } from '@/components/ui/toast/use-toast';
import { Calendar, Download, RefreshCw } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
    machineId: number;
}>();

const { toast } = useToast();
const calendarRef = ref(null);
const events = ref([]);
const loading = ref(false);
const scheduledCount = ref(0);
const completedCount = ref(0);

const calendarOptions = ref({
    plugins: [dayGridPlugin, interactionPlugin, timeGridPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    editable: true,
    selectable: true,
    dayMaxEvents: true,
    weekends: true,
    events: [],
    eventClick: handleEventClick,
    eventDrop: handleEventDrop,
    height: 'auto',
});

async function fetchEvents() {
    loading.value = true;
    try {
        const response = await axios.get(`/api/v1/machines/${props.machineId}/maintenance/calendar`);
        events.value = response.data.events;
        calendarOptions.value.events = response.data.events;
        scheduledCount.value = response.data.scheduled_count;
        completedCount.value = response.data.completed_count;
    } catch (error) {
        console.error('Failed to fetch calendar events:', error);
        toast({
            title: 'Error',
            description: 'Failed to load calendar events',
            variant: 'destructive',
        });
    } finally {
        loading.value = false;
    }
}

async function handleEventDrop(info: any) {
    // Only allow rescheduling future tasks, not historical logs
    if (info.event.extendedProps.type === 'completed') {
        info.revert();
        toast({
            title: 'Action Denied',
            description: 'Cannot reschedule completed maintenance logs.',
            variant: 'destructive',
        });
        return;
    }

    if (!confirm(`Reschedule "${info.event.title}" to ${info.event.start.toLocaleDateString()}?`)) {
        info.revert();
        return;
    }

    try {
        await axios.patch(`/api/v1/maintenance/schedules/${info.event.id}/reschedule`, {
            next_due_at: info.event.start.toISOString().split('T')[0],
        });
        
        toast({
            title: 'Rescheduled',
            description: 'Maintenance task has been rescheduled.',
        });
    } catch (error) {
        console.error('Failed to reschedule task:', error);
        info.revert();
        toast({
            title: 'Error',
            description: 'Failed to reschedule task',
            variant: 'destructive',
        });
    }
}

function handleEventClick(info: any) {
    const props = info.event.extendedProps;
    let description = '';
    
    if (props.type === 'completed') {
        description = `Performed by: ${props.performed_by}\nDuration: ${props.duration} min\nNotes: ${props.notes || 'None'}`;
    } else {
        description = `Priority: ${props.priority}\nAssigned to: ${props.assigned_to || 'Unassigned'}\nEst. Duration: ${props.estimated_duration || '?'} min\nDetails: ${props.description || 'none'}`;
        if (props.is_overdue) {
            description = `⚠️ OVERDUE!\n\n${description}`;
        }
    }

    alert(`${info.event.title}\n\n${description}`);
}

async function exportIcal() {
    try {
        const response = await axios.get(`/api/v1/machines/${props.machineId}/maintenance/export/ical`, {
            responseType: 'blob'
        });
        
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `maintenance-calendar-${Date.now()}.ics`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        
        toast({
            title: 'Export Successful',
            description: 'Calendar exported to iCal format.',
        });
    } catch (error) {
        console.error('Failed to export calendar:', error);
        toast({
            title: 'Export Failed',
            description: 'Failed to export calendar',
            variant: 'destructive',
        });
    }
}

onMounted(() => {
    fetchEvents();
});
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold flex items-center gap-2">
                    <Calendar class="h-4 w-4" />
                    Maintenance Calendar
                </h3>
                <p class="text-sm text-muted-foreground">
                    View upcoming schedules and completed history
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" @click="fetchEvents" :disabled="loading">
                    <RefreshCw class="h-4 w-4 mr-2" :class="{ 'animate-spin': loading }" />
                    Refresh
                </Button>
                <Button variant="outline" size="sm" @click="exportIcal">
                    <Download class="h-4 w-4 mr-2" />
                    Export iCal
                </Button>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap gap-4 text-xs">
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-full bg-red-600"></span>
                <span>Critical / Overdue</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-full bg-orange-600"></span>
                <span>High Priority</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-full bg-yellow-600"></span>
                <span>Medium Priority</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-full bg-green-600"></span>
                <span>Low Priority / Completed</span>
            </div>
        </div>

        <!-- Calendar -->
        <Card>
            <CardContent class="p-4">
                <FullCalendar :options="calendarOptions" class="demo-app-calendar" />
            </CardContent>
        </Card>
    </div>
</template>

<style>
/* Custom FullCalendar Styles for Dark Mode Compatibility */
.fc .fc-toolbar-title {
    font-size: 1.25rem;
    font-weight: 600;
}
.fc .fc-button-primary {
    background-color: hsl(var(--primary));
    border-color: hsl(var(--primary));
    color: hsl(var(--primary-foreground));
}
.fc .fc-button-primary:hover {
    background-color: hsl(var(--primary) / 0.9);
    border-color: hsl(var(--primary) / 0.9);
}
.fc .fc-button-primary:disabled {
    background-color: hsl(var(--muted));
    border-color: hsl(var(--muted));
    color: hsl(var(--muted-foreground));
}
.fc-day-today {
    background-color: hsl(var(--muted) / 0.3) !important;
}
.dark .fc-theme-standard td, 
.dark .fc-theme-standard th,
.dark .fc-theme-standard .fc-scrollgrid {
    border-color: hsl(var(--border));
}
.dark .fc .fc-list-day-cushion {
    background-color: hsl(var(--muted));
}
.dark .fc .fc-list-event:hover td {
    background-color: hsl(var(--muted) / 0.5);
}
</style>
