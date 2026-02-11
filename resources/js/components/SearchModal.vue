<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import { X, Search, Ticket, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const searchQuery = ref('');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);

let searchTimeout: any = null;

watch(searchQuery, (newQuery) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    
    if (newQuery.length < 2) {
        searchResults.value = [];
        return;
    }
    
    isSearching.value = true;
    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(`/api/search?q=${encodeURIComponent(newQuery)}`);
            const data = await response.json();
            searchResults.value = data.results || [];
        } catch (error) {
            console.error('Search failed:', error);
            searchResults.value = [];
        } finally {
            isSearching.value = false;
        }
    }, 300);
});

const goToResult = (result: any) => {
    router.visit(result.url);
    emit('close');
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') {
        emit('close');
    }
};
</script>

<template>
    <div 
        v-if="show"
        class="fixed inset-0 z-50 bg-black/50 flex items-start justify-center pt-20"
        @click.self="emit('close')"
        @keydown="handleKeydown"
    >
        <Card class="w-full max-w-2xl mx-4">
            <div class="p-6">
                <!-- Search Input -->
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search tickets, users, machines..."
                        class="pl-10 pr-10 h-12 text-base"
                        autofocus
                    />
                    <Button 
                        variant="ghost" 
                        size="icon" 
                        class="absolute right-1 top-1/2 -translate-y-1/2"
                        @click="emit('close')"
                    >
                        <X class="w-4 h-4" />
                    </Button>
                </div>

                <!-- Loading -->
                <div v-if="isSearching" class="flex items-center justify-center py-8 mt-4">
                    <Loader2 class="w-6 h-6 animate-spin text-muted-foreground" />
                </div>

                <!-- Results -->
                <div v-else-if="searchResults.length > 0" class="space-y-1 max-h-96 overflow-y-auto mt-4">
                    <button
                        v-for="result in searchResults"
                        :key="result.id"
                        @click="goToResult(result)"
                        class="w-full text-left p-3 rounded-lg hover:bg-accent transition-colors"
                    >
                        <div class="flex items-start gap-3">
                            <Ticket class="w-5 h-5 text-muted-foreground mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <div class="font-medium">{{ result.title }}</div>
                                <div class="text-sm text-muted-foreground truncate">{{ result.description }}</div>
                                <div class="text-xs text-muted-foreground mt-1">{{ result.type }}</div>
                            </div>
                        </div>
                    </button>
                </div>

                <!-- Empty State -->
                <div v-else-if="searchQuery.length >= 2" class="text-center py-8 mt-4 text-muted-foreground">
                    <p class="text-sm">No results found</p>
                </div>
            </div>
        </Card>
    </div>
</template>
