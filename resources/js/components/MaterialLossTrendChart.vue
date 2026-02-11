<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base sm:text-lg md:text-xl">
                <PackageX class="h-4 w-4 sm:h-5 sm:w-5" />
                Material Loss Trends
            </CardTitle>
            <CardDescription class="text-xs sm:text-sm">
                Track package waste, spillage, and material losses over time
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="flex items-center justify-center h-[300px] text-muted-foreground">
                <Loader2 class="h-8 w-8 animate-spin" />
            </div>
            
            <div v-else-if="!hasData" class="flex flex-col items-center justify-center h-[300px] text-muted-foreground">
                <PackageX class="h-12 w-12 mb-3 opacity-50" />
                <p class="font-medium">No Material Loss Data</p>
                <p class="text-sm">No material losses recorded for this period</p>
            </div>
            
            <div v-else class="space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div class="p-3 sm:p-4 rounded-lg bg-orange-50 border border-orange-200">
                        <div class="text-xs sm:text-xs font-semibold text-orange-700 uppercase mb-1">Total Quantity</div>
                        <div class="text-xl sm:text-2xl font-bold text-orange-900">{{ formatNumber(summary.total_quantity) }}</div>
                        <div class="text-xs text-orange-600 mt-1">{{ summary.total_count }} incidents</div>
                    </div>
                    <div class="p-3 sm:p-4 rounded-lg bg-blue-50 border border-blue-200">
                        <div class="text-xs sm:text-xs font-semibold text-blue-700 uppercase mb-1">Top Category</div>
                        <div class="text-base sm:text-lg font-bold text-blue-900 truncate">{{ topCategory?.category || 'N/A' }}</div>
                        <div class="text-xs text-blue-600 mt-1">{{ topCategory?.count || 0 }} incidents</div>
                    </div>
                </div>

                <!-- Trend Chart -->
                <div class="space-y-2">
                    <h4 class="text-sm sm:text-base font-semibold">Daily Quantity Trend</h4>
                    
                    <!-- Simple Bar Chart -->
                    <div class="h-[250px] flex items-end justify-center gap-2 border rounded-lg p-4 bg-muted/20">
                        <div 
                            v-for="point in displayTrendData" 
                            :key="point.date"
                            class="flex-1 flex flex-col items-center group max-w-16"
                        >
                            <div class="w-full bg-secondary/30 rounded-t relative flex flex-col justify-end overflow-hidden" style="height: 200px">
                                <div 
                                    class="w-full bg-gradient-to-t from-orange-500 to-orange-400 rounded-t transition-all hover:from-orange-600 hover:to-orange-500 cursor-pointer"
                                    :style="{ height: getBarHeight(point) + '%' }"
                                    :title="`${point.date}: ${point.quantity}`"
                                >
                                    <!-- Tooltip on hover -->
                                    <div class="absolute -top-12 left-1/2 transform -translate-x-1/2 bg-popover text-popover-foreground px-2 py-1 rounded text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-lg border">
                                        <div class="font-semibold">{{ formatDate(point.date) }}</div>
                                        <div>{{ point.quantity }} units</div>
                                        <div class="text-muted-foreground">{{ point.count }} incidents</div>
                                    </div>
                                </div>
                            </div>
                            <span class="text-[10px] sm:text-xs text-muted-foreground mt-1 font-mono">
                                {{ formatDateShort(point.date) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Category Breakdown -->
                <div class="space-y-2">
                    <h4 class="text-sm sm:text-base font-semibold">By Category</h4>
                    <div class="space-y-2">
                        <div 
                            v-for="category in categoryBreakdown" 
                            :key="category.category_code"
                            class="flex items-center gap-3 p-3 rounded-lg border bg-card hover:bg-accent/50 transition-colors"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-sm sm:text-base">{{ category.category }}</span>
                                    <Badge v-if="category.affects_oee" variant="outline" class="text-xs bg-orange-50 text-orange-700 border-orange-200">
                                        Affects OEE
                                    </Badge>
                                </div>
                                <div class="text-xs text-muted-foreground mt-1">
                                    {{ category.count }} incidents • {{ category.quantity }} {{ category.unit }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-base sm:text-lg font-bold text-orange-600">{{ category.quantity }}</div>
                            </div>
                        </div>
                        <div v-if="categoryBreakdown.length === 0" class="text-center text-sm text-muted-foreground py-4 italic">
                            No category data available
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { PackageX, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    summary: {
        total_quantity: number;
        total_cost: number;
        total_count: number;
        by_category: Array<{
            category: string;
            category_code: string;
            affects_oee: boolean;
            quantity: number;
            unit: string;
            cost: number;
            count: number;
        }>;
    };
    trendData: Array<{
        date: string;
        quantity: number;
        cost: number;
        count: number;
    }>;
    loading?: boolean;
}>();

// Cost tracking removed - only tracking quantity

// Track screen width for responsive display
const screenWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);

const updateScreenWidth = () => {
    screenWidth.value = window.innerWidth;
};

onMounted(() => {
    window.addEventListener('resize', updateScreenWidth);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateScreenWidth);
});

// Responsive trend data - show fewer points on mobile
const displayTrendData = computed(() => {
    if (!props.trendData || props.trendData.length === 0) return [];
    
    // Determine how many days to show based on screen size
    let daysToShow;
    if (screenWidth.value < 640) {
        // Mobile: show last 7 days
        daysToShow = 7;
    } else if (screenWidth.value < 1024) {
        // Tablet: show last 10 days
        daysToShow = 10;
    } else {
        // Desktop: show all data (up to 14 days)
        daysToShow = props.trendData.length;
    }
    
    // Return the most recent days
    return props.trendData.slice(-daysToShow);
});

const hasData = computed(() => {
    return props.trendData && props.trendData.length > 0;
});

const topCategory = computed(() => {
    if (!props.summary?.by_category || props.summary.by_category.length === 0) return null;
    return props.summary.by_category.reduce((max, cat) => 
        cat.quantity > (max?.quantity || 0) ? cat : max
    );
});

const categoryBreakdown = computed(() => {
    return props.summary?.by_category || [];
});

const getBarHeight = (point: any) => {
    if (!displayTrendData.value || displayTrendData.value.length === 0) return 0;
    
    const values = displayTrendData.value.map(p => p.quantity);
    const maxValue = Math.max(...values, 1);
    const value = point.quantity;
    
    return Math.max((value / maxValue) * 100, 4); // Minimum 4% height for visibility
};

const formatNumber = (num: number) => {
    if (!num) return '0';
    return num.toFixed(2);
};

const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatDateShort = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric' });
};
</script>
