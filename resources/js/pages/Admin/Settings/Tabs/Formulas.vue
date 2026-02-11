<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import type { InertiaForm } from '@inertiajs/vue3';

const props = defineProps<{
    settings: any[];
    form: InertiaForm<any>;
}>();

// Helper to get setting key by partial match if needed, but we know the keys.
// keys: formula_target_time_basis, formula_availability_exclude_breaks, formula_performance_include_rejects
</script>

<template>
    <div class="space-y-8 animated-content">
        <div>
            <h3 class="text-lg font-medium">Calculation Formulas</h3>
            <p class="text-sm text-muted-foreground mb-6">Configure how the system calculates key metrics like OEE and Targets.</p>

            <div class="grid gap-6">
                
                <!-- Production Target -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Production Target</CardTitle>
                        <CardDescription>Determines how the production goal (Target Count) is calculated for each shift.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label>Calculation Mode</Label>
                            <Select v-model="form.formula_target_mode">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Mode" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="static">Static (Database)</SelectItem>
                                    <SelectItem value="dynamic">Dynamic (Segmented Multi-Product)</SelectItem>
                                    <SelectItem value="custom">Custom Expression</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        
                        <div v-if="form.formula_target_mode === 'static'" class="space-y-4">
                            <p class="text-sm text-muted-foreground">
                                The target is retrieved directly from the `production_targets` table based on the machine/line configuration.
                            </p>
                        </div>

                        <div v-else-if="form.formula_target_mode === 'dynamic'" class="space-y-4">
                             <div class="p-4 bg-muted/40 rounded-lg border flex justify-center py-6 items-center flex-col gap-2">
                                 <div class="font-mono text-lg font-medium text-center">
                                    <span>Target = Σ (Run Time × Ideal Rate)</span>
                                    <span class="block text-xs text-muted-foreground mt-1">Calculated per product segment</span>
                                 </div>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Automatically handles multi-product shifts by splitting the shift into segments (based on changeovers) and calculating the target for each product separately.
                            </p>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="space-y-2">
                                <Label>Custom Expression</Label>
                                <textarea 
                                    v-model="form.formula_target_expression"
                                    class="w-full min-h-[100px] p-3 rounded-md border text-sm font-mono bg-background"
                                    placeholder="run_time * weighted_ideal_rate"
                                ></textarea>
                                <p class="text-xs text-muted-foreground">
                                    Available variables: <code>run_time</code>, <code>planned_production_time</code>, <code>weighted_ideal_rate</code>, <code>products_count</code>.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Availability Calculation -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Availability</CardTitle>
                        <CardDescription>Measures the percentage of scheduled time that the operation is available to operate.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label>Calculation Mode</Label>
                            <Select v-model="form.formula_availability_mode">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Mode" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="standard">Standard (Configurable)</SelectItem>
                                    <SelectItem value="custom">Custom Expression</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="form.formula_availability_mode === 'standard'" class="space-y-4">
                             <div class="p-4 bg-muted/40 rounded-lg border flex justify-center py-6 items-center flex-col gap-2">
                                 <div class="font-mono text-lg font-medium flex items-center">
                                    <span>Availability =&nbsp;</span>
                                    <div class="flex flex-col items-center text-center">
                                        <span class="border-b border-foreground/50 w-full px-2">Run Time</span>
                                        <span>Planned Production Time</span>
                                    </div>
                                    <span>&nbsp;× 100</span>
                                 </div>
                            </div>
                            <div class="flex items-center justify-between space-x-2 border p-4 rounded-lg">
                                <div class="flex flex-col space-y-1">
                                    <Label class="text-base">Exclude Planned Downtime (Breaks)</Label>
                                    <span class="text-xs text-muted-foreground">
                                        Enabled: PPT = Shift Length - Breaks.<br>Disabled: PPT = Shift Length.
                                    </span>
                                </div>
                                <Switch 
                                    :checked="form.formula_availability_exclude_breaks == '1' || form.formula_availability_exclude_breaks === true"
                                    @update:checked="(val: any) => form.formula_availability_exclude_breaks = val ? '1' : '0'"
                                />
                            </div>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="space-y-2">
                                <Label>Custom Expression</Label>
                                <textarea 
                                    v-model="form.formula_availability_expression"
                                    class="w-full min-h-[100px] p-3 rounded-md border text-sm font-mono bg-background"
                                    placeholder="(run_time / planned_production_time) * 100"
                                ></textarea>
                                <p class="text-xs text-muted-foreground">
                                    Available variables: <code>run_time</code>, <code>planned_production_time</code>, <code>total_shift_time</code>, <code>planned_downtime</code>, <code>unplanned_downtime</code>.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Performance Calculation -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Performance</CardTitle>
                        <CardDescription>Measures the speed at which the manufacturing process runs.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label>Calculation Mode</Label>
                            <Select v-model="form.formula_performance_mode">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Mode" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="standard">Standard (Configurable)</SelectItem>
                                    <SelectItem value="custom">Custom Expression</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="form.formula_performance_mode === 'standard'" class="space-y-4">
                            <div class="p-4 bg-muted/40 rounded-lg border flex justify-center py-6 items-center flex-col gap-2">
                                 <div class="font-mono text-lg font-medium flex items-center">
                                    <span>Performance =&nbsp;</span>
                                    <div class="flex flex-col items-center text-center">
                                        <span class="border-b border-foreground/50 w-full px-2">Total Count × Ideal Cycle Time</span>
                                        <span>Run Time</span>
                                    </div>
                                    <span>&nbsp;× 100</span>
                                 </div>
                            </div>
                            <div class="flex items-center justify-between space-x-2 border p-4 rounded-lg">
                                <div class="flex flex-col space-y-1">
                                    <Label class="text-base">Include Rejects in Standard Time</Label>
                                    <span class="text-xs text-muted-foreground">
                                        Enabled: Standard Time includes rejected parts.<br>Disabled: Only good parts count.
                                    </span>
                                </div>
                                <Switch 
                                    :checked="form.formula_performance_include_rejects == '1' || form.formula_performance_include_rejects === true"
                                    @update:checked="(val: any) => form.formula_performance_include_rejects = val ? '1' : '0'"
                                />
                            </div>
                        </div>

                         <div v-else class="space-y-4">
                            <div class="space-y-2">
                                <Label>Custom Expression</Label>
                                <textarea 
                                    v-model="form.formula_performance_expression"
                                    class="w-full min-h-[100px] p-3 rounded-md border text-sm font-mono bg-background"
                                    placeholder="(standard_time_produced / run_time) * 100"
                                ></textarea>
                                <p class="text-xs text-muted-foreground">
                                    Available variables: <code>standard_time_produced</code>, <code>run_time</code>, <code>good_count</code>, <code>reject_count</code>, <code>total_count</code>, <code>ideal_cycle_time</code>, <code>ideal_run_rate</code>.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Quality Calculation -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Quality</CardTitle>
                        <CardDescription>Measures the Good Units produced as a percentage of the Total Units Produced.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                             <Label>Calculation Mode</Label>
                            <Select v-model="form.formula_quality_mode">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Mode" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="standard">Standard (Good / Total)</SelectItem>
                                    <SelectItem value="custom">Custom Expression</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="form.formula_quality_mode === 'standard'" class="space-y-4">
                            <div class="p-4 bg-muted/40 rounded-lg border flex justify-center py-6 items-center">
                                 <div class="font-mono text-lg font-medium flex items-center">
                                    <span>Quality =&nbsp;</span>
                                    <div class="flex flex-col items-center text-center">
                                        <span class="border-b border-foreground/50 w-full px-2">Good Count</span>
                                        <span>Total Count</span>
                                    </div>
                                    <span>&nbsp;× 100</span>
                                 </div>
                            </div>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="space-y-2">
                                <Label>Custom Expression</Label>
                                <textarea 
                                    v-model="form.formula_quality_expression"
                                    class="w-full min-h-[100px] p-3 rounded-md border text-sm font-mono bg-background"
                                    placeholder="(good_count / total_count) * 100"
                                ></textarea>
                                <p class="text-xs text-muted-foreground">
                                    Available variables: <code>good_count</code>, <code>reject_count</code>, <code>total_count</code>.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

            </div>
        </div>
    </div>
</template>
