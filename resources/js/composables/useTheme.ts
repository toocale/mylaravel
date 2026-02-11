import { ref, computed, watch, onMounted } from 'vue';
import type { ThemeName, ThemeConfig } from '@/types/theme';
import { themes, getTheme } from '@/config/themes';

const STORAGE_KEY = 'oee_theme_preference';

// Global reactive state
const currentThemeName = ref<ThemeName>('default');

export function useTheme() {
    // Load theme from localStorage on mount
    onMounted(() => {
        const stored = localStorage.getItem(STORAGE_KEY) as ThemeName | null;
        if (stored && themes[stored]) {
            currentThemeName.value = stored;
            applyThemeClass(stored);
        }
    });

    const currentTheme = computed<ThemeConfig>(() => {
        return getTheme(currentThemeName.value);
    });

    const availableThemes = computed<ThemeConfig[]>(() => {
        return Object.values(themes);
    });

    const isOcean = computed(() => currentThemeName.value === 'ocean');
    const isIndustrial = computed(() => currentThemeName.value === 'industrial');
    const isMinimal = computed(() => currentThemeName.value === 'minimal');
    const isDefault = computed(() => currentThemeName.value === 'default');

    function setTheme(themeName: ThemeName) {
        if (!themes[themeName]) {
            console.warn(`Theme "${themeName}" not found, using default`);
            themeName = 'default';
        }

        currentThemeName.value = themeName;
        localStorage.setItem(STORAGE_KEY, themeName);
        applyThemeClass(themeName);
    }

    function applyThemeClass(themeName: ThemeName) {
        const config = getTheme(themeName);

        // Remove all theme classes
        Object.values(themes).forEach(theme => {
            if (theme.cssClass) {
                document.documentElement.classList.remove(theme.cssClass);
            }
        });

        // Add new theme class
        if (config.cssClass) {
            document.documentElement.classList.add(config.cssClass);
        }
    }

    // Watch for theme changes and apply class
    watch(currentThemeName, (newTheme) => {
        applyThemeClass(newTheme);
    });

    return {
        currentTheme,
        currentThemeName,
        availableThemes,
        setTheme,
        isOcean,
        isIndustrial,
        isMinimal,
        isDefault,
    };
}
