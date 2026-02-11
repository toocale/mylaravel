import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useTerminology() {
    const page = usePage();

    const plant = computed(() => {
        const settings = page.props.site as Record<string, string> | undefined;
        return settings?.asset_plant_name || 'Plant';
    });

    const line = computed(() => {
        const settings = page.props.site as Record<string, string> | undefined;
        return settings?.asset_line_name || 'Line';
    });

    const machine = computed(() => {
        const settings = page.props.site as Record<string, string> | undefined;
        return settings?.asset_machine_name || 'Machine';
    });

    // Singular/Plural helpers if needed, though for now we just append 's' broadly or precise if we add more settings
    // For simplicity, we'll assume plural is just +s or let the UI handle it for now, 
    // or we can add refined logic here.
    const plants = computed(() => `${plant.value}s`);
    const lines = computed(() => `${line.value}s`);
    const machines = computed(() => `${machine.value}s`);

    const siteName = computed(() => {
        const settings = page.props.site as Record<string, string> | undefined;
        return settings?.site_name || 'DAWA OEE';
    });

    return {
        plant,
        line,
        machine,
        plants,
        lines,
        machines,
        siteName
    };
}
