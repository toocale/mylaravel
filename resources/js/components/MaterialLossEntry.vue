<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { X, Plus } from 'lucide-vue-next';

const props = defineProps<{
    shiftId?: number;
    machineId?: number;
}>();

const emit = defineEmits<{
    (e: 'saved'): void;
}>();

const categories = ref<any[]>([]);
const lossEntries = ref<any[]>([]);

const form = useForm({
    losses: [] as any[],
});

const addLossEntry = () => {
    lossEntries.value.push({
        loss_category_id: null,
        quantity: null,
        reason: '',
        occurred_at: new Date().toISOString().slice(0, 16),
    });
};

const removeLossEntry = (index: number) => {
    lossEntries.value.splice(index, 1);
};

const saveLosses = () => {
    const losses = lossEntries.value.map(entry => ({
        ...entry,
        shift_id: props.shiftId,
        machine_id: props.machineId,
    }));
    
    form.losses = losses;
    
    form.post('/material-losses/batch', {
        onSuccess: () => {
            lossEntries.value = [];
            emit('saved');
        },
    });
};

const fetchCategories = async () => {
    try {
        const response = await fetch('/api/loss-categories');
        const data = await response.json();
        categories.value = data.categories;
    } catch (error) {
        console.error('Failed to fetch categories:', error);
    }
};

const getCategoryUnit = (categoryId: number) => {
    const category = categories.value.find(c => c.id === categoryId);
    return category?.unit || '';
};

const requiresReason = (categoryId: number) => {
    const category = categories.value.find(c => c.id === categoryId);
    return category?.requires_reason || false;
};

onMounted(() => {
    fetchCategories();
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Material Losses</h3>
            <Button @click="addLossEntry" size="sm" variant="outline">
                <Plus class="w-4 h-4 mr-2" />
                Add Loss
            </Button>
        </div>

        <div v-if="lossEntries.length === 0" class="text-center py-6 text-muted-foreground text-sm">
            No material losses recorded yet. Click "Add Loss" to start.
        </div>

        <div v-else class="space-y-3">
            <div 
                v-for="(entry, index) in lossEntries" 
                :key="index"
                class="border rounded-lg p-4 space-y-3"
            >
                <div class="flex items-start justify-between">
                    <h4 class="font-medium">Loss Entry #{{ index + 1 }}</h4>
                    <Button @click="removeLossEntry(index)" size="sm" variant="ghost">
                        <X class="w-4 h-4" />
                    </Button>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Category *</Label>
                        <select 
                            v-model="entry.loss_category_id"
                            class="w-full mt-1 h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            required
                        >
                            <option :value="null">Select Category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.code }} - {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <Label>Quantity * <span class="text-xs text-muted-foreground">({{ getCategoryUnit(entry.loss_category_id) }})</span></Label>
                        <Input 
                            v-model="entry.quantity"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            required
                        />
                    </div>
                </div>

                <div>
                    <Label>When *</Label>
                    <Input 
                        v-model="entry.occurred_at"
                        type="datetime-local"
                        required
                    />
                </div>

                <div v-if="entry.loss_category_id && requiresReason(entry.loss_category_id)">
                    <Label>Reason * <span class="text-xs text-red-500">(Required for this category)</span></Label>
                    <Textarea 
                        v-model="entry.reason"
                        rows="2"
                        placeholder="Explain the cause of this loss..."
                        required
                    />
                </div>
                <div v-else>
                    <Label>Reason (Optional)</Label>
                    <Textarea 
                        v-model="entry.reason"
                        rows="2"
                        placeholder="Explain the cause of this loss..."
                    />
                </div>
            </div>
        </div>

        <div v-if="lossEntries.length > 0" class="flex justify-end gap-3 pt-4 border-t">
            <Button @click="lossEntries = []" variant="outline">Clear All</Button>
            <Button @click="saveLosses" :disabled="form.processing">
                {{ form.processing ? 'Saving...' : `Save ${lossEntries.length} Loss${lossEntries.length > 1 ? 'es' : ''}` }}
            </Button>
        </div>
    </div>
</template>
