<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Factory, GitCommit, Monitor, Plus, Pencil, Settings } from 'lucide-vue-next';
import { useTerminology } from '@/composables/useTerminology';

const { plant: plantTerm, line: lineTerm, machine: machineTerm, plants: plantsTerm, lines: linesTerm, machines: machinesTerm } = useTerminology();

const props = defineProps<{
    plants: any[];
    canManage: (plantId: number) => boolean;
    onSelectContext: (plantId: number | null, lineId: number | null, machineId: number | null) => void;
    onOpenPlantDialog: (plant?: any) => void;
    onOpenLineDialog: (plantId: number, line?: any) => void;
    onOpenMachineDialog: (lineId: number, machine?: any) => void;
    selectedContext?: { plantId: number | null; lineId: number | null; machineId: number | null };
}>();

const selectedPlantId = ref<number | null>(null);
const selectedLineId = ref<number | null>(null);

// Sync with parent's selected context (for URL-restored state)
onMounted(() => {
    if (props.selectedContext) {
        selectedPlantId.value = props.selectedContext.plantId;
        selectedLineId.value = props.selectedContext.lineId;
    }
});

watch(() => props.selectedContext, (newCtx) => {
    if (newCtx) {
        selectedPlantId.value = newCtx.plantId;
        selectedLineId.value = newCtx.lineId;
    }
}, { deep: true });

const selectedPlant = computed(() => 
    props.plants.find(p => p.id === selectedPlantId.value)
);

const selectedLine = computed(() => 
    selectedPlant.value?.lines?.find((l: any) => l.id === selectedLineId.value)
);

const selectPlant = (plantId: number) => {
    selectedPlantId.value = plantId;
    selectedLineId.value = null;
    props.onSelectContext(plantId, null, null);
};

const selectLine = (lineId: number) => {
    selectedLineId.value = lineId;
    props.onSelectContext(selectedPlantId.value, lineId, null);
};

const selectMachine = (machineId: number) => {
    props.onSelectContext(selectedPlantId.value, selectedLineId.value, machineId);
};
</script>

<template>
    <div class="space-y-4 p-4">
        <!-- Breadcrumb Navigation -->
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <button 
                @click="selectedPlantId = null; selectedLineId = null; onSelectContext(null, null, null)"
                class="hover:text-foreground transition-colors"
            >
                All Plants
            </button>
            <span v-if="selectedPlant">›</span>
            <button 
                v-if="selectedPlant"
                @click="selectedLineId = null; onSelectContext(selectedPlantId, null, null)"
                class="hover:text-foreground transition-colors"
            >
                {{ selectedPlant.name }}
            </button>
            <span v-if="selectedLine">›</span>
            <span v-if="selectedLine" class="text-foreground font-medium">{{ selectedLine.name }}</span>
        </div>

        <!-- Level 1: Plants Grid (when no plant selected) -->
        <div v-if="!selectedPlantId">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold">Manufacturing {{ plantsTerm }}</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Select a {{ plantTerm.toLowerCase() }} to manage {{ linesTerm.toLowerCase() }} and {{ machinesTerm.toLowerCase() }}</p>
                </div>
                <Button @click="onOpenPlantDialog()" size="lg" class="gap-2">
                    <Plus class="h-4 w-4" />
                    Add {{ plantTerm }}
                </Button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                <Card 
                    v-for="plant in plants" 
                    :key="plant.id"
                    class="group cursor-pointer transition-all hover:shadow-xl hover:scale-[1.02] border-2 hover:border-primary/50 bg-gradient-to-br from-blue-50/50 to-cyan-50/50 dark:from-blue-950/20 dark:to-cyan-950/20"
                    @click="selectPlant(plant.id)"
                >
                    <CardHeader class="pb-2 p-3">
                        <div class="flex items-start justify-between">
                            <div class="p-2 rounded-lg bg-blue-500/10 ring-1 ring-blue-500/20">
                                <Factory class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <Button 
                                v-if="canManage(plant.id)"
                                variant="ghost" 
                                size="icon"
                                class="opacity-0 group-hover:opacity-100 transition-opacity h-6 w-6"
                                @click.stop="onOpenPlantDialog(plant)"
                            >
                                <Pencil class="h-3 w-3" />
                            </Button>
                        </div>
                        <CardTitle class="mt-2 text-base">{{ plant.name }}</CardTitle>
                        <CardDescription v-if="plant.location" class="text-xs">{{ plant.location }}</CardDescription>
                    </CardHeader>
                    <CardContent class="p-3 pt-0">
                        <div class="flex items-center gap-3 text-xs">
                            <div class="flex items-center gap-1">
                                <GitCommit class="h-3 w-3 text-orange-500" />
                                <span class="font-medium">{{ plant.lines?.length || 0 }}</span>
                                <span class="text-muted-foreground">{{ linesTerm }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <Monitor class="h-3 w-3 text-emerald-500" />
                                <span class="font-medium">{{ plant.lines?.reduce((acc: number, l: any) => acc + (l.machines?.length || 0), 0) || 0 }}</span>
                                <span class="text-muted-foreground">{{ machinesTerm }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State -->
                <Card v-if="plants.length === 0" class="col-span-full border-dashed border-2 bg-muted/20">
                    <CardContent class="flex flex-col items-center justify-center py-12">
                        <Factory class="h-16 w-16 text-muted-foreground/50 mb-4" />
                        <p class="text-muted-foreground mb-4">No plants configured yet</p>
                        <Button @click="onOpenPlantDialog()">
                            <Plus class="h-4 w-4 mr-2" />
                            Create Your First Plant
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Level 2: Lines Grid (when plant selected, no line selected) -->
        <div v-else-if="selectedPlant && !selectedLineId">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold">Production {{ linesTerm }}</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ selectedPlant.name }}</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="onOpenPlantDialog(selectedPlant)" class="gap-2">
                        <Settings class="h-4 w-4" />
                        {{ plantTerm }} Settings
                    </Button>
                    <Button @click="onOpenLineDialog(selectedPlant.id)" class="gap-2">
                        <Plus class="h-4 w-4" />
                        Add {{ lineTerm }}
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                <Card 
                    v-for="line in selectedPlant.lines" 
                    :key="line.id"
                    class="group cursor-pointer transition-all hover:shadow-lg hover:scale-[1.02] border-2 hover:border-orange-500/50 bg-gradient-to-br from-orange-50/50 to-amber-50/50 dark:from-orange-950/20 dark:to-amber-950/20"
                    @click="selectLine(line.id)"
                >
                    <CardHeader class="pb-2 p-3">
                        <div class="flex items-start justify-between">
                            <div class="p-2 rounded-lg bg-orange-500/10 ring-1 ring-orange-500/20">
                                <GitCommit class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                            </div>
                            <Button 
                                v-if="canManage(selectedPlant.id)"
                                variant="ghost" 
                                size="icon"
                                class="opacity-0 group-hover:opacity-100 transition-opacity h-6 w-6"
                                @click.stop="onOpenLineDialog(selectedPlant.id, line)"
                            >
                                <Pencil class="h-3 w-3" />
                            </Button>
                        </div>
                        <CardTitle class="mt-2 text-base">{{ line.name }}</CardTitle>
                    </CardHeader>
                    <CardContent class="p-3 pt-0">
                        <div class="flex items-center gap-1 text-xs">
                            <Monitor class="h-3 w-3 text-emerald-500" />
                            <span class="font-medium">{{ line.machines?.length || 0 }}</span>
                            <span class="text-muted-foreground">{{ machinesTerm }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State -->
                <Card v-if="!selectedPlant.lines || selectedPlant.lines.length === 0" class="col-span-full border-dashed border-2 bg-muted/20">
                    <CardContent class="flex flex-col items-center justify-center py-12">
                        <GitCommit class="h-16 w-16 text-muted-foreground/50 mb-4" />
                        <p class="text-muted-foreground mb-4">No production lines in this plant</p>
                        <Button @click="onOpenLineDialog(selectedPlant.id)">
                            <Plus class="h-4 w-4 mr-2" />
                            Add First Line
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Level 3: Machines Grid (when line selected) -->
        <div v-else-if="selectedLine">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold">{{ machinesTerm }}</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ selectedPlant?.name }} › {{ selectedLine.name }}</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="onOpenLineDialog(selectedPlant!.id, selectedLine)" class="gap-2">
                        <Settings class="h-4 w-4" />
                        {{ lineTerm }} Settings
                    </Button>
                    <Button @click="onOpenMachineDialog(selectedLine.id)" class="gap-2">
                        <Plus class="h-4 w-4" />
                        Add {{ machineTerm }}
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3">
                <Card 
                    v-for="machine in selectedLine.machines" 
                    :key="machine.id"
                    class="group cursor-pointer transition-all hover:shadow-lg hover:scale-[1.02] border-2 hover:border-emerald-500/50 bg-gradient-to-br from-emerald-50/50 to-teal-50/50 dark:from-emerald-950/20 dark:to-teal-950/20"
                    @click="selectMachine(machine.id)"
                >
                    <CardHeader class="pb-2 p-3">
                        <div class="flex items-start justify-between">
                            <div class="p-1.5 rounded-lg bg-emerald-500/10 ring-1 ring-emerald-500/20">
                                <Monitor class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <Button 
                                v-if="canManage(selectedPlant!.id)"
                                variant="ghost" 
                                size="icon"
                                class="opacity-0 group-hover:opacity-100 transition-opacity h-6 w-6"
                                @click.stop="onOpenMachineDialog(selectedLine.id, machine)"
                            >
                                <Pencil class="h-3 w-3" />
                            </Button>
                        </div>
                        <CardTitle class="mt-2 text-sm">{{ machine.name }}</CardTitle>
                        <div class="mt-1">
                            <Badge :variant="machine.status === 'running' ? 'default' : 'secondary'" class="text-[10px] px-1.5 py-0">
                                {{ machine.status }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="p-3 pt-0">
                        <div class="text-[10px] text-muted-foreground">
                            <div v-if="machine.default_ideal_rate">
                                Ideal Rate: {{ machine.default_ideal_rate }}/min
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State -->
                <Card v-if="!selectedLine.machines || selectedLine.machines.length === 0" class="col-span-full border-dashed border-2 bg-muted/20">
                    <CardContent class="flex flex-col items-center justify-center py-12">
                        <Monitor class="h-16 w-16 text-muted-foreground/50 mb-4" />
                        <p class="text-muted-foreground mb-4">No machines on this line</p>
                        <Button @click="onOpenMachineDialog(selectedLine.id)">
                            <Plus class="h-4 w-4 mr-2" />
                            Add First Machine
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
