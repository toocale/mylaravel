<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { TrendingUp, TrendingDown, Target } from 'lucide-vue-next';

const props = defineProps<{
    value: number;
    label: string;
    color?: string;
    size?: 'xs' | 'sm' | 'md' | 'lg';
    target?: number | null;
    showTarget?: boolean;
}>();

const { currentTheme, isOcean, isIndustrial, isMinimal } = useTheme();

// Size mappings
const sizeMap = {
    xs: { dimension: 80, strokeWidth: 6, fontSize: 'text-sm' },
    sm: { dimension: 120, strokeWidth: 8, fontSize: 'text-lg' },
    md: { dimension: 160, strokeWidth: 10, fontSize: 'text-2xl' },
    lg: { dimension: 200, strokeWidth: 12, fontSize: 'text-4xl' }
};

const config = computed(() => sizeMap[props.size || 'md']);
const radius = computed(() => (config.value.dimension - config.value.strokeWidth) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const offset = computed(() => circumference.value - (props.value / 100) * circumference.value);

// Determine color based on target achievement
const gaugeColor = computed(() => {
    if (props.color) return props.color;
    if (!props.target) return currentTheme.value.chartConfig.colors[0];
    return props.value >= props.target ? '#16a34a' : '#dc2626';
});

const isAboveTarget = computed(() => props.target && props.value >= props.target);
const targetDiff = computed(() => {
    if (!props.target) return null;
    return Math.abs(props.value - props.target).toFixed(1);
});

// Theme-specific rendering
const renderOceanGauge = computed(() => isOcean.value);
const renderIndustrialGauge = computed(() => isIndustrial.value);
const renderMinimalGauge = computed(() => isMinimal.value);
</script>

<template>
    <div class="flex flex-col items-center gap-2">
        <!-- OCEAN: Radial Progress with Gradient -->
        <div v-if="renderOceanGauge" class="relative">
            <svg :width="config.dimension" :height="config.dimension" class="transform -rotate-90">
                <!-- Background circle -->
                <circle
                    :cx="config.dimension / 2"
                    :cy="config.dimension / 2"
                    :r="radius"
                    fill="none"
                    stroke="currentColor"
                    :stroke-width="config.strokeWidth"
                    class="text-gray-200 dark:text-gray-700"
                />
                <!-- Progress circle with gradient -->
                <defs>
                    <linearGradient id="oceanGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" :style="`stop-color: ${currentTheme.chartConfig.colors[0]}`" />
                        <stop offset="100%" :style="`stop-color: ${currentTheme.chartConfig.colors[1]}`" />
                    </linearGradient>
                    <filter id="glow">
                        <feGaussianBlur stdDeviation="4" result="coloredBlur"/>
                        <feMerge>
                            <feMergeNode in="coloredBlur"/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                </defs>
                <circle
                    :cx="config.dimension / 2"
                    :cy="config.dimension / 2"
                    :r="radius"
                    fill="none"
                    stroke="url(#oceanGradient)"
                    :stroke-width="config.strokeWidth"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="offset"
                    stroke-linecap="round"
                    filter="url(#glow)"
                    class="transition-all duration-1000 ease-out"
                />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span :class="[config.fontSize, 'font-black text-foreground']">{{ value }}%</span>
                <span class="text-xs text-muted-foreground uppercase tracking-wide">{{ label }}</span>
            </div>
        </div>

        <!-- INDUSTRIAL: Semi-Circular Gauge -->
        <div v-else-if="renderIndustrialGauge" class="relative inline-block">
            <svg :width="config.dimension" :height="config.dimension * 0.75" viewBox="0 0 ${config.dimension} ${config.dimension * 0.75}" class="overflow-visible">
                <!-- Background arc (left to right semi-circle) -->
                <path
                    :d="`M ${config.strokeWidth/2} ${config.dimension * 0.5} A ${radius} ${radius} 0 0 1 ${config.dimension - config.strokeWidth/2} ${config.dimension * 0.5}`"
                    fill="none"
                    stroke="currentColor"
                    :stroke-width="config.strokeWidth"
                    class="text-gray-700"
                />
                <!-- Progress arc with neon effect -->
                <defs>
                    <filter id="neonGlow">
                        <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
                        <feMerge>
                            <feMergeNode in="coloredBlur"/>
                            <feMergeNode in="coloredBlur"/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                </defs>
                <!-- Calculate arc from left (180deg) sweeping clockwise based on percentage -->
                <path
                    :d="`M ${config.strokeWidth/2} ${config.dimension * 0.5} A ${radius} ${radius} 0 0 1 ${
                        config.dimension/2 + radius * Math.cos(Math.PI * (1 - value/100))
                    } ${
                        config.dimension * 0.5 - radius * Math.sin(Math.PI * (1 - value/100))
                    }`"
                    fill="none"
                    :stroke="gaugeColor"
                    :stroke-width="config.strokeWidth + 2"
                    stroke-linecap="round"
                    filter="url(#neonGlow)"
                    class="transition-all duration-1000 ease-out"
                />
                <!-- Indicator needle from center to percentage position -->
                <line
                    :x1="config.dimension / 2"
                    :y1="config.dimension * 0.5"
                    :x2="config.dimension/2 + (radius - 10) * Math.cos(Math.PI * (1 - value/100))"
                    :y2="config.dimension * 0.5 - (radius - 10) * Math.sin(Math.PI * (1 - value/100))"
                    :stroke="gaugeColor"
                    :stroke-width="3"
                    stroke-linecap="round"
                    class="transition-all duration-1000 ease-out"
                />
                <!-- Center dot for needle pivot -->
                <circle
                    :cx="config.dimension / 2"
                    :cy="config.dimension * 0.5"
                    :r="4"
                    :fill="gaugeColor"
                />
                <!-- Text inside SVG -->
                <text
                    :x="config.dimension / 2"
                    :y="config.dimension * 0.58"
                    text-anchor="middle"
                    :class="config.fontSize"
                    class="font-black fill-current text-foreground"
                    :style="`font-size: ${size === 'xs' ? '14px' : size === 'sm' ? '18px' : size === 'md' ? '24px' : '32px'}`"
                >{{ value }}%</text>
                <text
                    :x="config.dimension / 2"
                    :y="config.dimension * 0.58 + (size === 'xs' ? 12 : size === 'sm' ? 14 : size === 'md' ? 16 : 20)"
                    text-anchor="middle"
                    class="fill-current text-muted-foreground uppercase tracking-widest"
                    :style="`font-size: ${size === 'xs' || size === 'sm' ? '8px' : '9px'}; letter-spacing: 0.1em`"
                >{{ label }}</text>
            </svg>
        </div>

        <!-- MINIMAL: Clean Ring with Number -->
        <div v-else-if="renderMinimalGauge" class="relative">
            <svg :width="config.dimension" :height="config.dimension" class="transform -rotate-90">
                <!-- Outer ring (background) -->
                <circle
                    :cx="config.dimension / 2"
                    :cy="config.dimension / 2"
                    :r="radius"
                    fill="none"
                    stroke="currentColor"
                    :stroke-width="2"
                    class="text-border"
                />
                <!-- Progress ring -->
                <circle
                    :cx="config.dimension / 2"
                    :cy="config.dimension / 2"
                    :r="radius - 5"
                    fill="none"
                    :stroke="gaugeColor"
                    :stroke-width="config.strokeWidth"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="offset"
                    stroke-linecap="round"
                    class="transition-all duration-700 ease-in-out"
                />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span :class="[config.fontSize, 'font-bold text-foreground']">{{ value }}</span>
                <span class="text-xs text-muted-foreground mt-1">{{ label }}</span>
            </div>
        </div>

        <!-- DEFAULT: Modern Donut (fallback) -->
        <div v-else class="relative">
            <svg :width="config.dimension" :height="config.dimension" class="transform -rotate-90">
                <circle
                    :cx="config.dimension / 2"
                    :cy="config.dimension / 2"
                    :r="radius"
                    fill="none"
                    stroke="currentColor"
                    :stroke-width="config.strokeWidth"
                    class="text-gray-200 dark:text-gray-700"
                />
                <circle
                    :cx="config.dimension / 2"
                    :cy="config.dimension / 2"
                    :r="radius"
                    fill="none"
                    :stroke="gaugeColor"
                    :stroke-width="config.strokeWidth"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="offset"
                    stroke-linecap="round"
                    class="transition-all duration-1000 ease-out"
                />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span :class="[config.fontSize, 'font-black text-foreground']">{{ value }}%</span>
                <span class="text-xs text-muted-foreground">{{ label }}</span>
            </div>
        </div>

        <!-- Target indicator (all themes) -->
        <div v-if="target && showTarget" class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium"
             :class="isAboveTarget ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400'">
            <component :is="isAboveTarget ? TrendingUp : TrendingDown" class="h-3 w-3" />
            <span>Target: {{ target }}% ({{ isAboveTarget ? '+' : '-' }}{{ targetDiff }})</span>
        </div>
    </div>
</template>

<style scoped>
/* SVG animations are handled via transition classes, no keyframe needed */
</style>
