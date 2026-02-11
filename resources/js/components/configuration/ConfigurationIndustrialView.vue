<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { ChevronRight, ChevronLeft, Pencil, Trash2, Plus, Search, Factory, GitCommit, Monitor, Activity, Power, CornerUpLeft, Cpu } from 'lucide-vue-next';

const props = defineProps<{
    plants: any[];
    selectedContext: { plantId: number | null, lineId: number | null, machineId: number | null };
    canManage: (plantId: number) => boolean;
    onSelectContext: (plantId: number | null, lineId: number | null, machineId: number | null) => void;
    onOpenPlantDialog: (plant?: any) => void;
    onOpenLineDialog: (plantId: number, line?: any) => void;
    onOpenMachineDialog: (lineId: number, machine?: any) => void;
    onDeletePlant: (id: number) => void;
    onDeleteLine: (id: number) => void;
    onDeleteMachine: (id: number) => void;
}>();

// Navigation Helpers to determine current view level
const currentPlant = computed(() => props.plants.find(p => p.id === props.selectedContext.plantId));
const currentLine = computed(() => currentPlant.value?.lines?.find((l: any) => l.id === props.selectedContext.lineId));
const currentMachine = computed(() => currentLine.value?.machines?.find((m: any) => m.id === props.selectedContext.machineId));

const viewLevel = computed(() => {
    if (props.selectedContext.machineId) return 'MACHINE'; // Deepest level? Actually drill down stops at Line to show machines.
    if (props.selectedContext.lineId) return 'LINE'; // Showing Line Details + Machines list
    if (props.selectedContext.plantId) return 'PLANT'; // Showing Plant Details + Lines list
    return 'ROOT'; // Showing Plants list
});

// Calculate stats for current level
const currentStats = computed(() => {
    if (viewLevel.value === 'ROOT') return `${props.plants.length} ZONES`;
    if (viewLevel.value === 'PLANT') return `${currentPlant.value?.lines?.length || 0} SECTORS`;
    if (viewLevel.value === 'LINE') return `${currentLine.value?.machines?.length || 0} UNITS`;
    return '';
});

// Navigate Up
const goUp = () => {
    if (viewLevel.value === 'MACHINE') props.onSelectContext(props.selectedContext.plantId, props.selectedContext.lineId, null);
    else if (viewLevel.value === 'LINE') props.onSelectContext(props.selectedContext.plantId, null, null);
    else if (viewLevel.value === 'PLANT') props.onSelectContext(null, null, null);
};

// Selection Handlers (Drill Down)
const selectPlant = (id: number) => props.onSelectContext(id, null, null);
const selectLine = (id: number) => props.onSelectContext(props.selectedContext.plantId, id, null);
// Machine selection usually doesn't drill closer unless we want a specific machine view. 
// For this UI, selecting a machine highlighting it is enough, sidebar stays at Line view but highlights machine?
// Or maybe "Machine View" replaces the list with Machine Details? 
// Let's keep it at Line View (showing machine list) but highlight the machine.
const selectMachine = (id: number) => props.onSelectContext(props.selectedContext.plantId, props.selectedContext.lineId, id);

</script>

<template>
    <div class="h-full flex flex-col font-mono text-zinc-100 bg-black">
        <!-- Navigation / Header Bar -->
        <div class="p-4 border-b border-orange-500/30 bg-orange-950/20 shrink-0">
             <!-- Breadcrumb / Back Button -->
             <div class="flex items-center gap-2 mb-2">
                 <Button 
                    v-if="viewLevel !== 'ROOT'" 
                    @click="goUp()" 
                    variant="ghost" 
                    size="sm" 
                    class="h-7 px-2 -ml-2 text-orange-400 hover:text-orange-300 hover:bg-orange-500/10"
                >
                    <CornerUpLeft class="h-4 w-4 mr-1" /> BACK
                 </Button>
                 <span v-else class="text-xs font-bold text-orange-500 tracking-widest uppercase">SYSTEM ROOT</span>
             </div>

             <!-- Current Context Title -->
             <div class="flex items-center justify-between">
                 <div>
                     <h2 class="text-xl font-black tracking-tight text-white uppercase truncate max-w-[250px]">
                        {{ currentMachine?.name || currentLine?.name || currentPlant?.name || 'FACILITY OVERVIEW' }}
                     </h2>
                     <div class="text-[10px] text-zinc-500 mt-0.5 flex items-center gap-2">
                         <span class="bg-zinc-800 px-1 rounded text-zinc-300">{{ viewLevel }} LEVEL</span>
                         <span>{{ currentStats }}</span>
                     </div>
                 </div>
                 
                 <!-- Context Actions (Add Button) -->
                 <Button 
                    v-if="canManage(0)" 
                    @click="viewLevel === 'ROOT' ? onOpenPlantDialog() : (viewLevel === 'PLANT' ? onOpenLineDialog(currentPlant.id) : onOpenMachineDialog(currentLine.id))"
                    size="icon" 
                    class="h-9 w-9 bg-orange-600 hover:bg-orange-500 text-black border border-orange-400 rounded-sm"
                    :title="viewLevel === 'ROOT' ? 'Add Plant' : (viewLevel === 'PLANT' ? 'Add Line' : 'Add Machine')"
                >
                    <Plus class="h-5 w-5" />
                 </Button>
             </div>
        </div>

        <!-- Content Area: The List -->
        <div class="flex-1 overflow-y-auto p-2 space-y-2 custom-scrollbar">
            
            <!-- VIEW: ROOT (PLANTS LIST) -->
            <template v-if="viewLevel === 'ROOT'">
                <div v-if="plants.length === 0" class="p-8 text-center text-zinc-600 border border-dashed border-zinc-800 m-2">
                    NO ZONES DETECTED
                </div>
                <div 
                    v-for="plant in plants" 
                    :key="plant.id"
                    class="group relative bg-zinc-900 border border-zinc-800 hover:border-orange-500/50 hover:bg-zinc-800/80 transition-all cursor-pointer p-4 rounded-sm flex items-center justify-between"
                    @click="selectPlant(plant.id)"
                >
                    <div class="flex items-center gap-4">
                        <div class="bg-orange-900/20 p-2 rounded-sm border border-orange-500/10 group-hover:border-orange-500/30">
                            <Factory class="h-6 w-6 text-orange-500" />
                        </div>
                        <div>
                            <div class="font-bold text-lg text-zinc-200 group-hover:text-orange-100">{{ plant.name }}</div>
                            <div class="text-xs text-zinc-500">{{ plant.location || 'Unknown Location' }}</div>
                        </div>
                    </div>
                    <ChevronRight class="h-5 w-5 text-zinc-600 group-hover:text-orange-500" />
                    
                    <!-- Quick Actions -->
                    <div class="absolute right-10 top-1/2 -translate-y-1/2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity bg-black border border-zinc-800 p-1" v-if="canManage(plant.id)">
                        <button class="p-1 hover:text-blue-400 text-zinc-500" @click.stop="onOpenPlantDialog(plant)"><Pencil class="h-4 w-4"/></button>
                        <button class="p-1 hover:text-red-400 text-zinc-500" @click.stop="onDeletePlant(plant.id)"><Trash2 class="h-4 w-4"/></button>
                    </div>
                </div>
            </template>

            <!-- VIEW: PLANT (LINES LIST) -->
            <template v-else-if="viewLevel === 'PLANT'">
                <div v-if="!currentPlant?.lines?.length" class="p-8 text-center text-zinc-600 border border-dashed border-zinc-800 m-2">
                    SECTOR EMPTY - NO LINES
                </div>
                <div 
                    v-for="line in currentPlant?.lines" 
                    :key="line.id"
                    class="group relative bg-zinc-900 border border-zinc-800 hover:border-blue-500/50 hover:bg-zinc-800/80 transition-all cursor-pointer p-3 rounded-sm flex items-center justify-between"
                    @click="selectLine(line.id)"
                >
                     <div class="flex items-center gap-3">
                        <div class="bg-blue-900/20 p-2 rounded-sm border border-blue-500/10 group-hover:border-blue-500/30">
                            <GitCommit class="h-5 w-5 text-blue-500 rotate-90" />
                        </div>
                        <div>
                            <div class="font-bold text-zinc-200 group-hover:text-blue-100">{{ line.name }}</div>
                            <div class="text-[10px] text-zinc-500 flex items-center gap-2">
                                <span>ID: {{ line.id }}</span>
                                <span class="text-zinc-700">|</span>
                                <span>{{ line.machines?.length || 0 }} MACHINES</span>
                            </div>
                        </div>
                    </div>
                    <ChevronRight class="h-5 w-5 text-zinc-600 group-hover:text-blue-500" />

                    <!-- Quick Actions -->
                    <div class="absolute right-10 top-1/2 -translate-y-1/2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity bg-black border border-zinc-800 p-1" v-if="canManage(currentPlant!.id)">
                        <button class="p-1 hover:text-blue-400 text-zinc-500" @click.stop="onOpenLineDialog(currentPlant!.id, line)"><Pencil class="h-4 w-4"/></button>
                        <button class="p-1 hover:text-red-400 text-zinc-500" @click.stop="onDeleteLine(line.id)"><Trash2 class="h-4 w-4"/></button>
                    </div>
                </div>
            </template>

            <!-- VIEW: LINE (MACHINES LIST) -->
            <template v-else-if="viewLevel === 'LINE' || viewLevel === 'MACHINE'">
                 <div v-if="!currentLine?.machines?.length" class="p-8 text-center text-zinc-600 border border-dashed border-zinc-800 m-2">
                    NO EQUIPMENT INSTALLED
                </div>
                <div 
                    v-for="machine in currentLine?.machines" 
                    :key="machine.id"
                    class="group relative p-3 rounded-sm border cursor-pointer transition-all mb-2"
                    :class="props.selectedContext.machineId === machine.id 
                        ? 'bg-zinc-800/90 border-emerald-500/50 shadow-[0_0_15px_rgba(16,185,129,0.1)]' 
                        : 'bg-zinc-900 border-zinc-800 hover:border-emerald-500/30 hover:bg-zinc-800/50'"
                    @click="selectMachine(machine.id)"
                >
                     <div class="flex items-center justify-between mb-2">
                         <div class="flex items-center gap-2">
                             <Cpu class="h-4 w-4 text-emerald-500" />
                             <span class="font-bold text-sm tracking-wide" :class="props.selectedContext.machineId === machine.id ? 'text-emerald-400' : 'text-zinc-300'">{{ machine.name }}</span>
                         </div>
                         <div class="flex items-center gap-1.5">
                             <span class="w-1.5 h-1.5 rounded-full" :class="machine.status === 'running' ? 'bg-green-500 animate-pulse' : 'bg-zinc-600'"></span>
                             <span class="text-[10px] font-mono" :class="machine.status === 'running' ? 'text-green-500' : 'text-zinc-600'">{{ machine.status || 'OFFLINE' }}</span>
                         </div>
                     </div>
                     
                     <!-- Machine Stats/Info -->
                     <div class="grid grid-cols-2 gap-2 text-[10px] font-mono text-zinc-500 bg-black/30 p-2 rounded border border-zinc-800/50">
                         <div>RATE: <span class="text-zinc-300">{{ machine.default_ideal_rate || 0 }}</span></div>
                         <div>OEE: <span class="text-zinc-300">--%</span></div>
                     </div>

                     <!-- Selected Indicator -->
                     <div v-if="props.selectedContext.machineId === machine.id" class="absolute left-0 top-0 bottom-0 w-1 bg-emerald-500"></div>

                      <!-- Quick Actions -->
                    <div class="absolute right-2 top-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity" v-if="canManage(currentPlant!.id)">
                        <button class="p-1 hover:text-blue-400 text-zinc-500 bg-black/80 rounded" @click.stop="onOpenMachineDialog(currentLine!.id, machine)"><Pencil class="h-3.5 w-3.5"/></button>
                        <button class="p-1 hover:text-red-400 text-zinc-500 bg-black/80 rounded" @click.stop="onDeleteMachine(machine.id)"><Trash2 class="h-3.5 w-3.5"/></button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Footer / Status Bar -->
         <div class="p-2 bg-zinc-900 border-t border-zinc-800 text-[10px] font-mono text-zinc-600 flex justify-between items-center shrink-0">
             <span>SYSMODE: CONFIG_RW</span>
             <span>DAWAOEE.OS v2.0</span>
         </div>
    </div>
</template>

<style scoped>
/* Custom scrollbar for schematic view */
::-webkit-scrollbar {
  width: 4px;
}
::-webkit-scrollbar-track {
  background: #000; 
}
::-webkit-scrollbar-thumb {
  background: #333; 
}
::-webkit-scrollbar-thumb:hover {
  background: #f97316; 
}
</style>
