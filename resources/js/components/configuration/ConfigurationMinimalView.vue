<script setup lang="ts">
import { ref } from 'vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { ChevronRight, Factory, GitCommit, Monitor, Pencil, Plus } from 'lucide-vue-next';
import { useTerminology } from '@/composables/useTerminology';

const { plant: plantTerm, line: lineTerm, machine: machineTerm, plants: plantsTerm, lines: linesTerm, machines: machinesTerm } = useTerminology();

const props = defineProps<{
    plants: any[];
    canManage: (plantId: number) => boolean;
    onSelectContext: (plantId: number | null, lineId: number | null, machineId: number | null) => void;
    onOpenPlantDialog: (plant?: any) => void;
    onOpenLineDialog: (plantId: number, line?: any) => void;
    onOpenMachineDialog: (lineId: number, machine?: any) => void;
}>();

const openPlants = ref(new Set<number>());
const openLines = ref(new Set<number>());

const togglePlant = (plantId: number) => {
    if (openPlants.value.has(plantId)) {
        openPlants.value.delete(plantId);
    } else {
        openPlants.value.add(plantId);
    }
};

const toggleLine = (lineId: number) => {
    if (openLines.value.has(lineId)) {
        openLines.value.delete(lineId);
    } else {
        openLines.value.add(lineId);
    }
};
</script>

<template>
    <div class="max-w-4xl mx-auto p-8 space-y-12">
        <!-- Header -->
        <div class="space-y-4">
            <h1 class="text-3xl font-bold tracking-tight">Manufacturing Assets</h1>
            <p class="text-muted-foreground text-lg">Manage your {{ plantsTerm.toLowerCase() }}, production {{ linesTerm.toLowerCase() }}, and {{ machinesTerm.toLowerCase() }}</p>
        </div>

        <Separator />

        <!-- Plants Accordion -->
        <div class="space-y-8">
            <div v-for="plant in plants" :key="plant.id" class="space-y-4">
                <Collapsible :open="openPlants.has(plant.id)" @update:open="() => togglePlant(plant.id)">
                    <div class="group flex items-center justify-between py-6 px-8 rounded-2xl bg-muted/30 hover:bg-muted/50 transition-all border border-transparent hover:border-border">
                        <CollapsibleTrigger class="flex items-center gap-4 flex-1">
                            <div class="p-3 rounded-xl bg-primary/10">
                                <Factory class="h-6 w-6 text-primary" />
                            </div>
                            <div class="text-left flex-1">
                                <h3 class="text-xl font-semibold">{{ plant.name }}</h3>
                                <p class="text-sm text-muted-foreground mt-1" v-if="plant.location">{{ plant.location }}</p>
                                <div class="flex items-center gap-3 mt-2">
                                    <Badge variant="outline" class="text-xs">{{ plant.lines?.length || 0 }} {{ linesTerm }}</Badge>
                                    <Badge variant="outline" class="text-xs">
                                        {{ plant.lines?.reduce((acc: number, l: any) => acc + (l.machines?.length || 0), 0) || 0 }} {{ machinesTerm }}
                                    </Badge>
                                </div>
                            </div>
                            <ChevronRight 
                                class="h-5 w-5 text-muted-foreground transition-transform" 
                                :class="openPlants.has(plant.id) ? 'rotate-90' : ''"
                            />
                        </CollapsibleTrigger>
                        <div class="flex gap-2 ml-4" @click.stop>
                            <Button 
                                v-if="canManage(plant.id)"
                                variant="outline" 
                                size="sm"
                                @click="onOpenPlantDialog(plant)"
                            >
                                <Pencil class="h-4 w-4 mr-2" />
                                Edit
                            </Button>
                            <Button 
                                v-if="canManage(plant.id)"
                                variant="outline" 
                                size="sm"
                                @click="onOpenLineDialog(plant.id)"
                            >
                                <Plus class="h-4 w-4 mr-2" />
                                Add {{ lineTerm }}
                            </Button>
                        </div>
                    </div>

                    <CollapsibleContent class="pl-12 pt-4 space-y-6">
                        <!-- Lines Accordion -->
                        <div v-for="line in plant.lines" :key="line.id" class="space-y-3">
                            <Collapsible :open="openLines.has(line.id)" @update:open="() => toggleLine(line.id)">
                                <div class="group flex items-center justify-between py-5 px-6 rounded-xl bg-background hover:bg-muted/30 transition-all border border-border/50 hover:border-border">
                                    <CollapsibleTrigger class="flex items-center gap-3 flex-1">
                                        <div class="p-2 rounded-lg bg-orange-500/10">
                                            <GitCommit class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                                        </div>
                                        <div class="text-left flex-1">
                                            <h4 class="font-semibold">{{ line.name }}</h4>
                                            <p class="text-sm text-muted-foreground mt-0.5">
                                                {{ line.machines?.length || 0 }} {{ machinesTerm }}
                                            </p>
                                        </div>
                                        <ChevronRight 
                                            class="h-4 w-4 text-muted-foreground transition-transform" 
                                            :class="openLines.has(line.id) ? 'rotate-90' : ''"
                                        />
                                    </CollapsibleTrigger>
                                    <div class="flex gap-2 ml-3" @click.stop>
                                        <Button 
                                            v-if="canManage(plant.id)"
                                            variant="ghost" 
                                            size="sm"
                                            @click="onOpenLineDialog(plant.id, line)"
                                        >
                                            <Pencil class="h-3.5 w-3.5 mr-2" />
                                            Edit
                                        </Button>
                                        <Button 
                                            v-if="canManage(plant.id)"
                                            variant="ghost" 
                                            size="sm"
                                            @click="onOpenMachineDialog(line.id)"
                                        >
                                            <Plus class="h-3.5 w-3.5 mr-2" />
                                            Add
                                        </Button>
                                    </div>
                                </div>

                                <CollapsibleContent class="pl-8 pt-3 space-y-2">
                                    <!-- Machines List -->
                                    <div 
                                        v-for="machine in line.machines" 
                                        :key="machine.id"
                                        class="group flex items-center justify-between py-4 px-5 rounded-lg bg-background hover:bg-muted/20 transition-all cursor-pointer border border-transparent hover:border-border/50"
                                        @click="onSelectContext(plant.id, line.id, machine.id)"
                                    >
                                        <div class="flex items-center gap-3 flex-1">
                                            <div class="p-1.5 rounded bg-emerald-500/10">
                                                <Monitor class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                            </div>
                                            <div class="flex-1">
                                                <p class="font-medium text-sm">{{ machine.name }}</p>
                                                <p class="text-xs text-muted-foreground mt-0.5" v-if="machine.default_ideal_rate">
                                                    {{ machine.default_ideal_rate }} pcs/min
                                                </p>
                                            </div>
                                            <Badge :variant="machine.status === 'running' ? 'default' : 'secondary'" class="text-xs">
                                                {{ machine.status }}
                                            </Badge>
                                        </div>
                                        <Button 
                                            v-if="canManage(plant.id)"
                                            variant="ghost" 
                                            size="sm"
                                            class="opacity-0 group-hover:opacity-100 transition-opacity"
                                            @click.stop="onOpenMachineDialog(line.id, machine)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>

                                    <!-- Empty State -->
                                    <div v-if="!line.machines || line.machines.length === 0" class="py-8 text-center">
                                        <p class="text-sm text-muted-foreground mb-3">No {{ machinesTerm.toLowerCase() }} on this {{ lineTerm.toLowerCase() }}</p>
                                        <Button 
                                            variant="outline" 
                                            size="sm"
                                            @click="onOpenMachineDialog(line.id)"
                                        >
                                            <Plus class="h-3.5 w-3.5 mr-2" />
                                            Add First {{ machineTerm }}
                                        </Button>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>

                        <!-- Empty Lines State -->
                        <div v-if="!plant.lines || plant.lines.length === 0" class="py-8 text-center">
                            <p class="text-sm text-muted-foreground mb-3">No production {{ linesTerm.toLowerCase() }} in this {{ plantTerm.toLowerCase() }}</p>
                            <Button 
                                variant="outline"
                                @click="onOpenLineDialog(plant.id)"
                            >
                                <Plus class="h-4 w-4 mr-2" />
                                Add First {{ lineTerm }}
                            </Button>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                <Separator class="my-8" />
            </div>

            <!-- Empty Plants State -->
            <div v-if="plants.length === 0" class="py-16 text-center">
                <Factory class="h-16 w-16 text-muted-foreground/30 mx-auto mb-4" />
                <h3 class="text-lg font-semibold mb-2">No {{ plantsTerm }} Configured</h3>
                <p class="text-muted-foreground mb-6">Get started by creating your first manufacturing {{ plantTerm.toLowerCase() }}</p>
                <Button size="lg" @click="onOpenPlantDialog()">
                    <Plus class="h-5 w-5 mr-2" />
                    Create First {{ plantTerm }}
                </Button>
            </div>

            <!-- Add Plant Button (when plants exist) -->
            <div v-if="plants.length > 0" class="pt-4">
                <Button variant="outline" size="lg" @click="onOpenPlantDialog()" class="w-full">
                    <Plus class="h-5 w-5 mr-2" />
                    Add Another {{ plantTerm }}
                </Button>
            </div>
        </div>
    </div>
</template>
