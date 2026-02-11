<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Palette, Check } from 'lucide-vue-next';
import { computed } from 'vue';

const { currentTheme, currentThemeName, availableThemes, setTheme } = useTheme();

const handleThemeChange = (themeName: string) => {
    setTheme(themeName as any);
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="group h-9 w-9 cursor-pointer"
                :title="`Current theme: ${currentTheme.displayName}`"
            >
                <Palette class="size-5 opacity-80 group-hover:opacity-100" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-64">
            <DropdownMenuLabel class="text-sm font-semibold">Theme Selection</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuRadioGroup :model-value="currentThemeName" @update:model-value="handleThemeChange">
                <DropdownMenuRadioItem
                    v-for="theme in availableThemes"
                    :key="theme.name"
                    :value="theme.name"
                    class="cursor-pointer"
                >
                    <div class="flex flex-col gap-1 py-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ theme.displayName }}</span>
                            <Check
                                v-if="currentThemeName === theme.name"
                                class="h-4 w-4 text-primary ml-auto"
                            />
                        </div>
                        <span class="text-xs text-muted-foreground">{{ theme.description }}</span>
                    </div>
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
